<?php

namespace Chayka\SEO;

use Chayka\Helpers\FsHelper;
use Chayka\WP\Helpers\AclHelper;
use Chayka\WP\MVC\Controller;
use Chayka\Helpers\InputHelper;
use Chayka\WP\Helpers\JsonHelper;

class SitemapController extends Controller{

    public function init(){
        // NlsHelper::load('main');
        // InputHelper::captureInput();
    }

    public function indexAction(){
        $sitemapIndex = SitemapHelper::buildSitemap();
        die($sitemapIndex);
    }

    public function buildAction(){
        AclHelper::apiPermissionRequired();
        SitemapHelper::buildSitemap();
        JsonHelper::respondSuccess('Sitemap was successfully built');
    }

    public function flushAction(){
        AclHelper::apiPermissionRequired();
        SitemapHelper::flushSitemap();
        JsonHelper::respondSuccess('Sitemap was successfully flushed');
    }

    public function patchRobotsAction(){
        SitemapHelper::patchRobotsTxt();
        JsonHelper::respondSuccess('Robots.txt was successfully patched');
    }

}