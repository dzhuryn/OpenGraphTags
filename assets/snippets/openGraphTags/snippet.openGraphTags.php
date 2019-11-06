<?php
require_once MODX_BASE_PATH.'assets/snippets/openGraphTags/openGraphTags.php';
require_once MODX_BASE_PATH.'assets/snippets/DocLister/lib/DLTemplate.class.php';

$share = new openGraphTags($modx,$params);

if($params['api'] == '1'){
    echo json_encode($share->getFields());
}
else if( $share->permission === true){
    echo $share->render();
}

return;