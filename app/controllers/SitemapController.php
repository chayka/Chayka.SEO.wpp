<?php

namespace Chayka\SEO;

use Chayka\Helpers\FsHelper;
use Chayka\WP\MVC\Controller;
use Chayka\Helpers\InputHelper;
use Chayka\WP\Helpers\JsonHelper;

class SitemapController extends Controller{

    public function init(){
        // NlsHelper::load('main');
        // InputHelper::captureInput();
    }

    public function indexAction(){
        set_time_limit(0);
        SitemapHelper::ensureCacheDir();
        $indexFn = SitemapHelper::getSitemapIndexPath(true);
        if(file_exists($indexFn)){
            die(file_get_contents($indexFn));
        }

        $maxEntryPackSize = OptionHelper::getOption('maxEntryPackSize', 10);

        $barrels = SitemapHelper::getBarrels($maxEntryPackSize);

        foreach($barrels as $barrelData){
            $barrelFn = SitemapHelper::getSitemapBarrelPath($barrelData->post_type, $barrelData->barrel, true);
            if(!file_exists($barrelFn)){
                file_put_contents($barrelFn, SitemapHelper::renderPostTypePackIndex($barrelData->post_type, $barrelData->barrel, $maxEntryPackSize));
            }
        }

        $sitemapIndex = SitemapHelper::renderSitemapIndex($barrels);

        file_put_contents($indexFn, $sitemapIndex);

        die($sitemapIndex);
    }

} 