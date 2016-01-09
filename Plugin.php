<?php

namespace Chayka\SEO;

use Chayka\WP;

class Plugin extends WP\Plugin{

    /* chayka: constants */
    
    public static $instance = null;

    public static function init(){
        if(!static::$instance){
            static::$instance = $app = new self(__FILE__, array(
                'sitemap'
                /* chayka: init-controllers */
            ));
            $app->dbUpdate(array());
	        $app->addSupport_UriProcessing();
	        $app->addSupport_ConsolePages();
	        $app->addSupport_Metaboxes();
	        $app->addSupport_PostProcessing(100);



            /* chayka: init-addSupport */
        }
    }


    /**
     * Register your action hooks here using $this->addAction();
     */
    public function registerActions() {
    	/* chayka: registerActions */
    }

    /**
     * Register your action hooks here using $this->addFilter();
     */
    public function registerFilters() {
		/* chayka: registerFilters */
    }

    /**
     * Register scripts and styles here using $this->registerScript() and $this->registerStyle()
     *
     * @param bool $minimize
     */
    public function registerResources($minimize = false) {
        $this->registerBowerResources(true);

        $this->populateResUrl('<%= appName %>');

        $this->setResSrcDir('src/');
        $this->setResDistDir('dist/');

		/* chayka: registerResources */
    }

    /**
     * Routes are to be added here via $this->addRoute();
     */
    public function registerRoutes() {
        $this->addRoute('default');
    }

    /**
     * Registering console pages
     */
    public function registerConsolePages(){
        $this->addConsolePage('SEO', 'update_core', 'seo', '/admin/seo', 'dashicons-admin-generic', '75.45830508344807');

        /* chayka: registerConsolePages */
    }
    
    /**
     * Add custom metaboxes here via addMetaBox() calls;
     */
    public function registerMetaBoxes(){
        /* chayka: registerMetaBoxes */
    }

    /**
     * Remove registered metaboxes here via removeMetaBox() calls;
     */
    public function unregisterMetaBoxes(){
        /* chayka: unregisterMetaBoxes */
    }

    /**
     * Custom Sidebars are to be added here via $this->registerSidebar();
     */
    public function registerSidebars() {
		/* chayka: registerSidebars */
    }
    
    /* postProcessing */

    /**
     * This function should be triggered on post insert / update / delete
     *
     * @param $postId
     */
    public function updateSitemapBarrelForPostId($postId){
        $post = WP\Models\PostModel::selectById($postId);
        $postType = $post->getType();
        $enabled = OptionHelper::getOption('sitemap_need_type_' . $postType);
        if($enabled){
            $packSize = OptionHelper::getOption('maxEntryPackSize', 10);
            $barrel = floor($postId / $packSize);
            $barrelFn = SitemapHelper::getSitemapBarrelPath($postType, $barrel, true);
            $xml = SitemapHelper::renderPostTypePackIndex($postType, $barrel, $packSize);
            file_put_contents($barrelFn, $xml);
            $indexFn = SitemapHelper::getSitemapIndexPath(true);
            unlink($indexFn);
        }

    }

    /**
     * This is a hook for save_post
     *
     * @param integer $postId
     * @param \WP_Post $post
     */
    public function savePost($postId, $post){
        $this->updateSitemapBarrelForPostId($postId);
    }
    
    /**
     * This is a hook for delete_post
     *
     * @param integer $postId
     */
    public function deletePost($postId){
        $this->updateSitemapBarrelForPostId($postId);
    }
    
    /**
     * This is a hook for trashed_post
     *
     * @param integer $postId
     */
    public function trashedPost($postId){
        $this->deletePost($postId);
    }
}