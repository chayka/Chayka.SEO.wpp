<?php

namespace Chayka\SEO;

use Chayka\WP\MVC\Controller;
use Chayka\Helpers\InputHelper;
use Chayka\WP\Helpers\JsonHelper;

class SitemapController extends Controller{

    public function init(){
        // NlsHelper::load('main');
        // InputHelper::captureInput();
    }

    public function indexAction(){
        SitemapHelper::renderIndex();
    }

} 