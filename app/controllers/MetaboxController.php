<?php

namespace Chayka\SEO;

use Chayka\Helpers\InputHelper;
use Chayka\WP\MVC\Controller;
use Chayka\WP\Models\PostModel;

class MetaboxController extends Controller{

    public function init(){
        global $post;

        $action = InputHelper::getParam('action');
        wp_nonce_field($action, $action.'_nonce' );

        $richPost = PostModel::unpackDbRecord($post);

        $this->view->assign('post', $richPost);

		$this->enqueueNgScriptStyle('chayka-wp-admin');
    }

    public function seoAction(){

    }
}