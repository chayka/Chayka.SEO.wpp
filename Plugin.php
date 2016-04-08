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
        $this->addAction('user_register', 'saveUser', 100);
        $this->addAction('profile_update', 'saveUser', 100);
        $this->addAction('delete_user', 'deleteUser', 100);

        $this->addAction('created_term', 'saveTerm', 100, 3);
        $this->addAction('edited_term', 'saveTerm', 100, 3);
        $this->addAction('delete_term', 'deleteTerm', 100, 3);

        if(OptionHelper::getOption('renderMetaFields')){
            $this->addAction('wp_head', 'renderMeta');
        }
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
     * Add custom metaboxes here via addMetabox() calls;
     */
    public function registerMetaboxes(){
        if(OptionHelper::getOption('renderMetaFields')){
            $this->addMetabox('seo', 'SEO', '/metabox/seo', 'normal', 'high', null);
        }

        /* chayka: registerMetaboxes */
    }

    /**
     * Remove registered metaboxes here via removeMetabox() calls;
     */
    public function unregisterMetaboxes(){
        /* chayka: unregisterMetaboxes */
    }

    /**
     * Custom Sidebars are to be added here via $this->registerSidebar();
     */
    public function registerSidebars() {
		/* chayka: registerSidebars */
    }

    /**
     * Render meta tags if enabled
     */
    public function renderMeta(){
        global $post;
        $view = self::getView();

        $description = WP\Helpers\HtmlHelper::getMetaDescription();
        if(!$description){
            if(is_single() || is_page()){
                $description = get_post_meta($post->ID, 'description', true);
                if(!$description){
                    $description = get_the_excerpt();
                }
            }
            if(is_tax()){
                $description = term_description();
            }
        }
        if(!$description){
            $description = OptionHelper::getOption('defaultDescription');
        }

        $view->assign('description', $description);

        $keywords = WP\Helpers\HtmlHelper::getMetaKeywords();
        if(!$keywords){
            if(is_single() || is_page()){
                $keywords = get_post_meta($post->ID, 'keywords', true);
                if(!$keywords){
                    $richPost = WP\Models\PostModel::unpackDbRecord($post);
                    $terms = $richPost->loadTerms();
                    $keywords = [];
                    if($terms){
                        foreach($terms as $taxonomy=>$ts){
                            $keywords = array_merge($keywords, $ts);
                        }
                    }
                    $keywords = array_unique($keywords);
                    $keywords = join(', ', $keywords);
                }
            }
        }
        if(!$keywords){
            $keywords = OptionHelper::getOption('defaultKeywords');
        }

        $view->assign('keywords', $keywords);

        echo $view->render('seo/meta.phtml');
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
            $barrelFn = SitemapHelper::getSitemapPostsBarrelPath($postType, $barrel, true);
            $xml = SitemapHelper::renderPostTypePackIndex($postType, $barrel, $packSize);
            file_put_contents($barrelFn, $xml);
            $indexFn = SitemapHelper::getSitemapIndexPath(true);
            unlink($indexFn);
        }

    }

    /**
     * This function should be triggered on post insert / update / delete
     *
     * @param $userId
     */
    public function updateSitemapBarrelForUserId($userId){
        $enabled = OptionHelper::getOption('sitemap_need_users');
        if($enabled){
            $packSize = OptionHelper::getOption('maxEntryPackSize', 10);
            $barrel = floor($userId / $packSize);
            $barrelFn = SitemapHelper::getSitemapUsersBarrelPath($barrel, true);
            $xml = SitemapHelper::renderUsersPackIndex($barrel, $packSize);
            file_put_contents($barrelFn, $xml);
            $indexFn = SitemapHelper::getSitemapIndexPath(true);
            unlink($indexFn);
        }

    }

    /**
     * This function should be triggered on post insert / update / delete
     *
     * @param $termTaxonomyId
     * @param $taxonomy
     */
    public function updateSitemapBarrelForTerm($termTaxonomyId, $taxonomy){
        $enabled = OptionHelper::getOption('sitemap_need_taxonomy_' . $taxonomy);
        if($enabled){
            $packSize = OptionHelper::getOption('maxEntryPackSize', 10);
            $barrel = floor($termTaxonomyId / $packSize);
            $barrelFn = SitemapHelper::getSitemapTaxonomiesBarrelPath($taxonomy, $barrel, true);
            $xml = SitemapHelper::renderTaxonomyPackIndex($taxonomy, $barrel, $packSize);
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

    /**
     * This is a hook for user_register, profile_update
     *
     * @param integer $userId
     */
    public function saveUser($userId){
        $this->updateSitemapBarrelForPostId($userId);
    }

    /**
     * This is a hook for delete_user
     *
     * @param integer $userId
     */
    public function deleteUser($userId){
        $this->updateSitemapBarrelForPostId($userId);
    }

    /**
     * This is a hook for created_term, edited_term
     *
     * @param integer $termId
     * @param integer $termTaxonomyId
     * @param string $taxonomy
     */
    public function saveTerm($termId, $termTaxonomyId, $taxonomy){
        $this->updateSitemapBarrelForTerm($termTaxonomyId, $taxonomy);
    }

    /**
     * This is a hook for deleted_term
     *
     * @param integer $termId
     * @param integer $termTaxonomyId
     * @param string $taxonomy
     */
    public function deleteTerm($termId, $termTaxonomyId, $taxonomy){
        $this->updateSitemapBarrelForTerm($termTaxonomyId, $taxonomy);
    }

}