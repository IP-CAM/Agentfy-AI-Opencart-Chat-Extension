<?php 
$_['module_agentfy_setting'] = array(
	'api_key' => '',
    'api_url' => 'https://api.agentfy.ai/v1',
    'index_out_of_stock' => false,
    'index_disabled_products' => false,
    'admin_only_access' => false,
    'topseller_count' => 10,
    'bestseller_count' => 100,
    "product_template" => "# %title%\r\n%bestseller%\r\n%topseller%\r\n%description%\r\n\r\n### Attributes\r\n* Model: %model%\r\n* Image URL: %image%\r\n* Quantity: %quantity%\r\n* Product page URL: %seoUrl%\r\n* Price: %price%\r\n* Category: %categories%\r\n* Tags: %tag%\r\n* Manufacturer: %manufacturer%\r\n%attributes%",
    'category_template' => "# %title%\r\n%description%\r\n\r\n### Attributes\r\n* Category page URL: %seoUrl%",
    'manufacturer_template' => "# %title%\r\n### Attributes\r\n* Manufacturer page URL: %seoUrl%"
);

$_['module_agentfy_display'] = array(
    'welcomeMessage' => array(),
	'button' => array(
        "title"=> "Support",
        "icon"=> "",
        "animation" => "ping",
        "color"=> "black",
        "size"=> "md",
        "type"=> "rounded",
        "shadow"=> "none",
        "position" => array(
            "bottom" => "15px",
             "right" => "15px"
        )
    )
);
?>