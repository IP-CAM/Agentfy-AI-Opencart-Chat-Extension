<?php
class ModelExtensionAgentfyProducts extends Model {

    private $store_url = '';
	private $catalog_url = '';

	public function __construct($registry) {
		parent::__construct($registry);


		if ($this->request->server['HTTPS']) {
            $this->store_url = HTTPS_SERVER;
            $this->catalog_url = HTTPS_CATALOG;
        } else {
            $this->store_url = HTTP_SERVER;
            $this->catalog_url = HTTP_CATALOG;
        }
	}


	public function index($sourceId, $last_step, $store_id) {

		$count = $this->db->query("SELECT COUNT(*) AS `c` FROM `" . DB_PREFIX . "product`");
		$countItems = $count->row['c'];
		$limit = 25;
		$products = $this->db->query( "SELECT * FROM `" . DB_PREFIX . "product` LIMIT " . ( $limit * $last_step ) . ', ' . $limit );

		foreach ($products->rows as $product) {
			$this->indexProduct($product['product_id'], $sourceId, $store_id, false);
		}
		return $countItems;
	}

	public function indexProduct($productId, $sourceId, $store_id, $index = true) {
		$this->load->model('catalog/manufacturer');
		$this->load->model('catalog/product');
		$this->load->model('catalog/category');
		$this->load->model('design/seo_url');
		$this->load->model('extension/agentfy/api');


		$document = $this->model_extension_agentfy_api->getDocument($sourceId, $productId, $store_id);
		$productInfo = $this->model_catalog_product->getProduct($productId);
		$seo_url_data = $this->model_design_seo_url->getSeoUrlsByQuery('product_id='.$productId);

        $this->load->model("setting/setting");
        $module_setting = $this->model_setting_setting->getSetting(
            "module_agentfy",
            $store_id
        );
		$setting = $module_setting["module_agentfy_setting"];
        

        if (!$setting['index_out_of_stock'] && $productInfo['quantity'] <= 0) {
            return;
        }

        if (!$setting['index_disabled_products'] && $productInfo['status'] == 0) {
            return;
        }
        $template = $setting['product_template'];

        if (empty($template)) {
            throw new Exception("Product Template is empty");
            return;
        }
        $metadata = [];
        $metadata["title"] = html_entity_decode($productInfo['name'],ENT_QUOTES, 'UTF-8');

		$url = $this->catalog_url.'index.php?route=product/product&product_id='.$productId;
		if ($seo_url_data) {
			$url = $this->catalog_url . $seo_url_data[0]['keyword'];
		}
        $metadata["source"] = $url;

        $isBestseller = $this->isBestSellerProduct($productId, $store_id, $setting['bestseller_count']);
        $isTopseller = $this->isTopSellerProduct($productId, $store_id, $setting['topseller_count']);
		$search = [
			'%title%',
			'%description%',
			'%model%',
			'%price%',
			'%quantity%',
			'%seoUrl%',
            '%tag%',
            '%bestseller%',
            '%topseller%',
		];
		$replace = [
			html_entity_decode($productInfo['name'],ENT_QUOTES, 'UTF-8'),
			strip_tags(html_entity_decode($productInfo['description'],ENT_QUOTES, 'UTF-8')),
			$productInfo['model'],
			$this->currency->format($productInfo['price'], $this->config->get('config_currency')),
			$productInfo['quantity'],
			$url,
            $productInfo['tag'],
            $isBestseller ? 'Bestseller' : '',
            $isTopseller && !$isBestseller ? 'TopSeller' : ''
		];

		$pageContent = [
			html_entity_decode($productInfo['name'],ENT_QUOTES, 'UTF-8'),
			strip_tags(html_entity_decode($productInfo['description'],ENT_QUOTES, 'UTF-8')),
			$productInfo['model'],
			$this->currency->format($productInfo['price'], $this->config->get('config_currency')),
			'quantity - '.$productInfo['quantity'],
			$url
		];
		if (!empty($productInfo['image'])) {
			array_push($search, '%image%');
			array_push($replace, $this->catalog_url."image/".$productInfo['image']);
            $metadata["image"] = $this->catalog_url."image/".$productInfo['image'];
		} else {
			array_push($search, '%image%');
			array_push($replace, 'N/A');
		}

		// Attributes
		$this->load->model('catalog/attribute');

		$product_attributes = $this->model_catalog_product->getProductAttributes($productId);
		$attributes = '';
		foreach ($product_attributes as $product_attribute) {
			$attribute_info = $this->model_catalog_attribute->getAttribute($product_attribute['attribute_id']);

			if ($attribute_info) {
				$attributes .= $attribute_info['name']. ': '.$product_attribute['product_attribute_description'][$this->config->get('config_language_id')]['text'].PHP_EOL;
			}
		}

		array_push($search, '%attributes%');
		array_push($replace, $attributes);

		$manufacturer_info = $this->model_catalog_manufacturer->getManufacturer($productInfo['manufacturer_id']);

		array_push($search, '%manufacturer%');
		array_push($replace, !empty($manufacturer_info) ? $manufacturer_info['name'] : 'N/A');

		$categories = $this->model_catalog_product->getProductCategories($productId);
		$categoriesResult = [];
		foreach ($categories as $category_id) {
			$category_info = $this->model_catalog_category->getCategory($category_id);

			if ($category_info) {
				$categoryPath = ($category_info['path']) ? $category_info['path'] . ' > ' . $category_info['name'] : $category_info['name'];
				$decodedCategoryPath = html_entity_decode($categoryPath);

				array_push($categoriesResult, $decodedCategoryPath);
			}
		}

		array_push($search, '%categories%');
		array_push($replace, join("\n", $categoriesResult));

		$pageContent = str_replace($search, $replace, $template);

		if (!empty($document)) {
			$date1 = new DateTime($productInfo['date_modified']);
			$date2 = new DateTime($document['updatedAt']);

			if ($date1 > $date2) {
                $this->model_extension_agentfy_api->updateDocument(
                    $sourceId,
                    $document['id'],
                    $document['summary'],
                    $productId,
                    html_entity_decode($productInfo['name'],ENT_QUOTES, 'UTF-8'),
                    $pageContent,
                    $metadata,
					$store_id
                );
                if($index){
                    $this->model_extension_agentfy_api->indexDocument($sourceId, $document['id'], $store_id);
                }
	        }
		} else {
			$this->model_extension_agentfy_api->addDocument(
				$sourceId,
				$productId,
				html_entity_decode($productInfo['name'],ENT_QUOTES, 'UTF-8'),
				$pageContent,
                $metadata,
				$store_id
			);
            if($index){
                $document = $this->model_extension_agentfy_api->getDocument($sourceId, $productId, $store_id);
                $this->model_extension_agentfy_api->indexDocument($sourceId, $document['id'], $store_id);
            }
		}
	}

    public function isBestSellerProduct($productId, $store_id = 0, $count = 100) {
        $query = $this->db->query("SELECT op.product_id, SUM(op.quantity) AS total FROM " . DB_PREFIX . "order_product op LEFT JOIN `" . DB_PREFIX . "order` o ON (op.order_id = o.order_id) LEFT JOIN `" . DB_PREFIX . "product` p ON (op.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE o.order_status_id > '0' AND p.status = '1' AND p.date_available <= NOW() AND op.product_id = '".(int)$productId."' AND p2s.store_id = '" . (int)$store_id . "' GROUP BY op.product_id HAVING total > '".(int)$count."' ORDER BY total DESC LIMIT 1");

		return count($query->rows) > 0;
	}

    public function isTopSellerProduct($productId, $store_id = 0, $count = 10) {
        $query = $this->db->query("SELECT op.product_id, SUM(op.quantity) AS total FROM " . DB_PREFIX . "order_product op LEFT JOIN `" . DB_PREFIX . "order` o ON (op.order_id = o.order_id) LEFT JOIN `" . DB_PREFIX . "product` p ON (op.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE o.order_status_id > '0' AND p.status = '1' AND p.date_available <= NOW() AND op.product_id = '".(int)$productId."' AND p2s.store_id = '" . (int)$store_id . "' GROUP BY op.product_id HAVING total > '".(int)$count."' ORDER BY total DESC LIMIT 1");

		return count($query->rows) > 0;
	}
}
