<?php
/**
  * @package Module
  */

/**
  * Breadcrumb Parameter
  */
define('MODULE_BREADCRUMB_PARAM', '_b');

/**
  * @package Core
  */
abstract class Module {

  protected $id = 'none';
  protected $moduleName = '';
  protected $hasFeeds = false;
  protected $feedFields = array();
  
  protected $session;
  
  protected $page = 'index';
  protected $args = array();

  protected $templateModule = 'none'; 
  protected $templatePage = 'index';
  
  protected $pagetype = 'unknown';
  protected $platform = 'unknown';
  protected $supportsCerts = false;
  
  protected $imageExt = '.png';
  
  private $pageConfig = null;
  
  private $pageTitle           = 'No Title';
  private $breadcrumbTitle     = 'No Title';
  private $breadcrumbLongTitle = 'No Title';
  
  private $inlineCSSBlocks = array();
  private $externalCSSURLs = array();
  private $inlineJavascriptBlocks = array();
  private $inlineJavascriptFooterBlocks = array();
  private $onOrientationChangeBlocks = array();
  private $onLoadBlocks = array('scrollTo(0,1);');
  private $externalJavascriptURLs = array();

  private $moduleDebugStrings = array();
  
  private $breadcrumbs = array();

  private $fontsize = 'medium';
  private $fontsizes = array('small', 'medium', 'large', 'xlarge');
  
  private $templateEngine = null;
  private $siteVars = null;
  
  private $htmlPager = null;
  private $inPagedMode = true;
  
  private $tabbedView = null;
  
  protected $cacheMaxAge=0;
  
  protected $autoPhoneNumberDetection = true;
  
  public function getID()
  {
    return $this->id;
  }
  
  //
  // Tabbed View support
  //
  
  protected function enableTabs($tabKeys, $defaultTab=null, $javascripts=array()) {
    $currentTab = $tabKeys[0];
    if (in_array($this->getArg('tab'), $tabKeys)) {
      $currentTab = $this->getArg('tab');
      
    } else if (isset($defaultTab) && in_array($defaultTab, $tabKeys)) {
      $currentTab = $defaultTab;
    }
    
    $tabs = array();
    foreach ($tabKeys as $tabKey) {
      $title = ucwords($tabKey);
      $configKey = "tab_{$tabKey}";
      if (isset($this->pageConfig, $this->pageConfig[$configKey]) && 
          strlen($this->pageConfig[$configKey])) {
        $title = $this->pageConfig[$configKey];
      }
      
      $tabArgs = $this->args;
      $tabArgs['tab'] = $tabKey;
      
      $tabs[$tabKey] = array(
        'title' => $title,
        'url'   => $this->buildBreadcrumbURL($this->page, $tabArgs, false),
        'javascript' => isset($javascripts[$tabKey]) ? $javascripts[$tabKey] : '',
      );
    }
    
    $this->tabbedView = array(
      'tabs'       => $tabs,
      'current'    => $currentTab,
    );

    $this->addInlineJavascriptFooter("showTab('{$currentTab}Tab');");
  }
  
  //
  // Pager support
  // Note: the first page is 0 (0 ... pageCount-1)
  //
  protected function enablePager($html, $encoding, $pageNumber) {
    $this->htmlPager = new HTMLPager($html, $encoding, $pageNumber);
  }
  
  // Override in subclass if you are using the pager
  protected function urlForPage($pageNumber) {
    return '';
  }
    
  private function getPager() {
    $pager = array(
      'pageNumber'   => $this->htmlPager->getPageNumber(),
      'pageCount'    => $this->htmlPager->getPageCount(),
      'inPagedMode'  => $this->htmlPager->getPageNumber() != HTMLPager::ALL_PAGES,
      'html' => array(
        'all'  => $this->htmlPager->getAllPagesHTML(),
        'page' => $this->htmlPager->getPageHTML(),
      ),
      'url' => array(
        'prev'  => null,
        'next'  => null,
        'all'   => $this->urlForPage(HTMLPager::ALL_PAGES),
        'pages' => array(),
      ),
    );

    for ($i = 0; $i < $pager['pageCount']; $i++) {
      $pager['url']['pages'][] = $this->urlForPage($i).
        $this->getBreadcrumbArgString('&', false);
    }
        
    if ($pager['pageNumber'] > 0) {
      $pager['url']['prev'] = $pager['url']['pages'][$pager['pageNumber']-1];
    }
    
    if ($pager['pageNumber'] < ($pager['pageCount']-1)) {
      $pager['url']['next'] = $pager['url']['pages'][$pager['pageNumber']+1];
    }
    
    return $pager;
  }
  
  //
  // Font size controls
  //
  private function getFontSizeCSS() {
    switch ($this->fontsize) {
      case 'small':
        return 'body { font-size: 89%; line-height: 1.33em }';
      case 'large':
        return 'body { font-size: 125%; line-height: 1.33em }';
      case 'xlarge':
        return 'body { font-size: 150%; line-height: 1.33em }';
      default:
        return 'body { font-size: 100%; line-height: 1.33em }';
    }
  }
   
  private function getFontSizeURLs() {
    $urls = array();
    
    $args = $this->args;
    foreach ($this->fontsizes as $fontsize) {
      $args['font'] = $fontsize;
      $urls[$fontsize] = self::buildURL($this->page, $args);
    }
    return $urls;
  }

  //
  // Minify URLs
  //
  private function getMinifyUrls() {
    $page = preg_replace('/[\s-]+/', '+', $this->page);
    $minKey = "{$this->id}-{$page}-{$this->pagetype}-{$this->platform}-".md5(SITE_DIR);
    $minDebug = $this->getSiteVar('MINIFY_DEBUG') ? '&debug=1' : '';
    
    return array(
      'css' => "/min/g=css-$minKey$minDebug",
      'js'  => "/min/g=js-$minKey$minDebug",
    );
  }

  //
  // Google Analytics for non-Javascript devices
  //
  private function googleAnalyticsGetImageUrl($gaID) {
    if (isset($gaID) && strlen($gaID)) {
      $url = '/ga.php?';
      $url .= "utmac=$gaID";
      $url .= '&utmn=' . rand(0, 0x7fffffff);
  
      $referer = $this->argVal($_SERVER, 'HTTP_REFERER');
      $path    = $this->argVal($_SERVER, 'REQUEST_URI');
  
      if (!isset($referer)) {
        $referer = '-';
      }
      $url .= '&utmr=' . urlencode($referer);
  
      if (isset($path)) {
        $url .= '&utmp=' . urlencode($path);
      }
  
      $url .= '&guid=ON';

      return $url;
      
    } else {
      return '';
    }
  }

  //
  // Lazy load
  //
  private function loadTemplateEngineIfNeeded() {
    if (!isset($this->templateEngine)) {
      $this->templateEngine = new TemplateEngine($this->id);
    }
  }
  
  //
  // URL helper functions
  //
  protected static function argVal($args, $key, $default=null) {
    if (isset($args[$key])) {
      return $args[$key];
    } else {
      return $default;
    }
  }
  
  protected function getArg($key, $default='') {
    return self::argVal($this->args, $key, $default);
  }

  protected static function buildURL($page, $args=array()) {
    $argString = '';
    if (isset($args) && count($args)) {
      $argString = http_build_query($args);
    }
    
    return sprintf("%s%s", $page, (strlen($argString) ? "?$argString" : ""));
  }

  public static function buildURLForModule($id, $page, $args=array()) {
    return sprintf("%s%s/%s", URL_BASE, $id, self::buildURL($page, $args));
  }
  
  protected function buildMailToLink($to, $subject, $body) {
    $to = trim($to);
    
    if ($to == '' && $GLOBALS['deviceClassifier']->mailToLinkNeedsAtInToField()) {
      $to = '@';
    }

    $url = "mailto:{$to}?".http_build_query(array("subject" => $subject, 
                                                  "body"    => $body));
    
    // mailto url's do not respect '+' (as space) so we convert to %20
    $url = str_replace('+', '%20', $url); 
    
    return $url;
  }

  public function redirectToModule($id, $args=array()) {
  
    $url = sprintf("%s%s/?%s", URL_BASE, $id, http_build_query($args));
    //error_log('Redirecting to: '.$url);
    
    header("Location: $url");
    exit;
  }

  protected function redirectTo($page, $args=null, $preserveBreadcrumbs=false) {
    if (!isset($args)) { $args = $this->args; }
    
    $url = '';
    if ($preserveBreadcrumbs) {
      $url = $this->buildBreadcrumbURL($page, $args, false);
    } else {
      $url = self::buildURL($page, $args);
    }
    
    //error_log('Redirecting to: '.$url);
    header("Location: $url");
    exit;
  }
    
  //
  // Configuration
  //
  protected function getSiteVar($var, $opts=Config::LOG_ERRORS)
  {
    
      return $GLOBALS['siteConfig']->getVar($var, $opts | Config::EXPAND_VALUE);
  }

  protected function getSiteSection($var, $opts=Config::LOG_ERRORS)
  {
      return $GLOBALS['siteConfig']->getSection($var, $opts);
  }

  protected function getModuleVar($var, $default=null, $opts=Config::LOG_ERRORS)
  {
     $config = $this->getModuleConfig();
     $value = $config->getVar($var, Config::EXPAND_VALUE| $opts);
     return is_null($value) ? $default :$value;
  }

  protected function getModuleSection($section, $default=array(), $opts=Config::LOG_ERRORS)
  {
     $config = $this->getModuleConfig();
     if (!$section = $config->getSection($section, $opts)) {
        $section = $default;
     }
     return $section;
  }

  public function getAPIVar($var, $default=null)
  {
     $config = $this->getAPIConfig();
     $value = $config->getVar($var, Config::EXPAND_VALUE);
     return is_null($value) ? $default :$value;
  }

  public function getAPISection($section, $default=array())
  {
     $config = $this->getAPIConfig();
     if (!$section = $config->getSection($section)) {
        $section = $default;
     }
     return $section;
  }

  protected function getModuleArray($section)
  {
     $config = $this->getModuleConfig();
     $return = array();
     
     if ($data = $config->getSection($section)) {
        $fields = array_keys($data);
        
        for ($i=0; $i<count($data[$fields[0]]); $i++) {
            $item = array();
            foreach ($fields as $field) {
                $item[$field] = $data[$field][$i];
            }
            $return[] = $item;
        }
     } 
     
     return $return;
  }

  public function hasFeeds()
  {
     return $this->hasFeeds;
  }
  
  public function getFeedFields()
  {
     return $this->feedFields;
  }
  
  public function removeFeed($index)
  {
       $feedData = $this->loadFeedData();
       if (isset($feedData[$index])) {
           unset($feedData[$index]);
           if (is_numeric($index)) {
              $feedData = array_values($feedData);
           }
           
           $this->saveConfig(array('feeds'=>$feedData), 'feeds');
           
       }
  }
  
  public function addFeed($newFeedData, &$error=null)
  {
       $feedData = $this->loadFeedData();
       if (!isset($newFeedData['TITLE']) || empty($newFeedData['TITLE'])) {
         $error = "Feed Title cannot be blank";
         return false;
       }

       if (isset($newFeedData['BASE_URL']) && empty($newFeedData['BASE_URL'])) {
         $error = "Feed URL cannot be blank";
         return false;
       }
       
       if (isset($newFeedData['LABEL'])) {
          $label = $newFeedData['LABEL'];
          unset($newFeedData['LABEL']);
          $feedData[$label] = $newFeedData;
       } else {
          $feedData[] = $newFeedData;
       }
       
       return $this->saveConfig(array('feeds'=>$feedData), 'feeds');
       
  }
  
  protected function loadFeedData() {
    $data = null;
    $feedConfigFile = realpath_exists(sprintf("%s/feeds/%s.ini", SITE_CONFIG_DIR, $this->id));
    if ($feedConfigFile) {
        $data = parse_ini_file($feedConfigFile, true);
    } 
    
    return $data;
  }
  
  //
  // Admin Methods
  //
  //
  
  protected function prepareAdminForSection($section, $adminModule) {
    switch ($section)
    {
        case 'strings':
            $strings = $this->getModuleSection('strings');
            $formListItems = array();
            foreach ($strings as $string=>$value) {
                $item = array(
                    'label'=>implode(" ", array_map("ucfirst", explode("_", strtolower($section)))),
                    'name'=>"moduleData[strings][$string]",
                    'typename'=>"moduleData][strings][$string",
                    'value'=>is_array($value) ? implode("\n\n", $value) : $value,
                    'type'=>is_array($value) ? 'paragraph' : 'text'
                );
                
                $formListItems[] = $item;
            }
            $adminModule->assign('formListItems' ,$formListItems);
            break;
    }
  }
  
  public function createDefaultConfigFile()
  {
    $moduleConfig = $this->getConfig($this->id, 'module', ConfigFile::OPTION_CREATE_EMPTY);
    $moduleConfig->addSectionVars($this->getModuleDefaultData());
    return $moduleConfig->saveFile();
  }
  
  protected function saveConfig($moduleData, $section=null)
  {
        switch ($section)
        {
            case 'feeds':
            case 'nav':
                $type = $section;
                break;
            default:
                $type = 'module';
                break;
        }

        $moduleConfigFile = ConfigFile::factory($this->id, $type, ConfigFile::OPTION_CREATE_EMPTY);
        
        switch ($section)
        {
            case 'feeds':
            case 'nav':
                $moduleData = $moduleData[$section];
                // clear out empty values
                foreach ($moduleData as $feed=>$feedData) {
                    foreach ($feedData as $var=>$value) {
                        if (strlen($value)==0) {
                            unset($moduleData[$feed][$var]);
                        }
                    }
                }
                $moduleConfigFile->setSectionVars($moduleData);
                break;
            default:
                $moduleConfigFile->addSectionVars($moduleData, !$section);
        }
        
        $moduleConfigFile->saveFile();
  }
  
  //
  // Factory function
  // instantiates objects for the different modules
  //
  public static function factory($id, $page='', $args=array()) {
    $className = ucfirst($id).'Module';

    $modulePaths = array(
      THEME_DIR."/modules/$id/Theme{$className}.php"=>"Theme" .$className,
      SITE_DIR."/modules/$id/Site{$className}.php"=>"Site" .$className,
      MODULES_DIR."/$id/$className.php"=>$className
    );
    
    foreach($modulePaths as $path=>$className){ 
      $moduleFile = realpath_exists($path);
      if ($moduleFile && include_once($moduleFile)) {
        $module = new $className();
        if ($page) {
            $module->factoryInit($page, $args);
        }
        $module->initialize();
        return $module;
      }
    }
    throw new PageNotFound("Module '$id' not found while handling '{$_SERVER['REQUEST_URI']}'");
   }
   
   private function factoryInit($page, $args)
   {
        $moduleData = $this->getModuleData();
        $this->moduleName = $moduleData['title'];
        
        $disabled = self::argVal($moduleData, 'disabled', false);
        if ($disabled) {
            $this->redirectToModule('error', array('code'=>'disabled', 'url'=>$_SERVER['REQUEST_URI']));
        }
        
        $secure = self::argVal($moduleData, 'secure', false);
        if ($secure && (!isset($_SERVER['HTTPS']) || ($_SERVER['HTTPS'] !='on'))) { 
            // redirect to https (at this time, we are assuming it's on the same host)
             $redirect= "https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
             header("Location:$redirect");    
             exit();
        }
        
        if ($this->getSiteVar('AUTHENTICATION_ENABLED')) {
            $user = $this->getUser();
            $session = $this->getSession();
            $protected = self::argVal($moduleData, 'protected', false);
            if ($protected) {
                if (!$this->isLoggedIn()) {
                    $this->redirectToModule('error', array('code'=>'protected', 'url'=>URL_BASE . 'login/?' .
                        http_build_query(array('url'=>$_SERVER['REQUEST_URI']))));
                }
            }
            
            $acls = $this->getAccessControlLists();
            $allow = count($acls) > 0 ? false : true; // if there are no ACLs then access is allowed
            foreach ($acls as $acl) {
                $result = $acl->evaluateForUser($user);
                switch ($result)
                {
                    case AccessControlList::RULE_ACTION_ALLOW:
                        $allow = true;
                        break;
                    case AccessControlList::RULE_ACTION_DENY:
                        $this->redirectToModule('error', array('code'=>'protectedACL', 'url'=>URL_BASE . 'login/?' .
                            http_build_query(array('url'=>$_SERVER['REQUEST_URI']))));
                        break;
                }
            }
            
            if (!$allow) {
                $this->redirectToModule('error', array('code'=>'protectedACL', 'url'=>URL_BASE . 'login/?' .
                    http_build_query(array('url'=>$_SERVER['REQUEST_URI']))));
            }
        }
        
        $this->setPage($page);
        $this->setTemplatePage($this->page, $this->id);
        $this->args = $args;
        $this->setAutoPhoneNumberDetection($GLOBALS['siteConfig']->getVar('AUTODETECT_PHONE_NUMBERS'));
        
        $this->pagetype      = $GLOBALS['deviceClassifier']->getPagetype();
        $this->platform      = $GLOBALS['deviceClassifier']->getPlatform();
        $this->supportsCerts = $GLOBALS['deviceClassifier']->getSupportsCerts();
        
        // Pull in fontsize
        if (isset($args['font'])) {
          $this->fontsize = $args['font'];
          setcookie('fontsize', $this->fontsize, time() + $this->getSiteVar('LAYOUT_COOKIE_LIFESPAN'), COOKIE_PATH);      
        
        } else if (isset($_COOKIE['fontsize'])) { 
          $this->fontsize = $_COOKIE['fontsize'];
        }

        switch ($this->pagetype) {
          case 'compliant':
            $this->imageExt = '.png';
            break;
            
          case 'touch':
          case 'basic':
            $this->imageExt = '.gif';
            break;
        }
  }
  
  protected function setAutoPhoneNumberDetection($bool)
  {
    $this->autoPhoneNumberDetection = $bool ? true : false;
    $this->assign('autoPhoneNumberDetection', $this->autoPhoneNumberDetection);
  }
  
  public function getSession()
  {
    if (!$this->session) {
        $this->session = new Session();
    }
    
    return $this->session;
  }
  
  public function getModuleName()
  {
    return $this->moduleName;
  }
  
  protected function getModuleDefaultData()
  {
    return array(
        'title'=>$this->moduleName,
        'disabled'=>0,
        'protected'=>0,
        'search'=>0,
        'secure'=>0
    );
  }
  
  protected function getSectionTitleForKey($key)
  {
     switch ($key)
     {
            case 'strings':
                return 'Strings';
     }
     return $key;
  }

  protected function getModuleItemForKey($key, $value)
  {
    $item = array(
        'label'=>implode(" ", array_map("ucfirst", explode("_", strtolower($key)))),
        'name'=>"moduleData[$key]",
        'typename'=>"moduleData][$key",
        'value'=>$value,
        'type'=>'text'
    );

    switch ($key)
    {
        case 'display_type':
            $item['label'] = 'Display type';
            $item['type'] = 'radio';
            $item['options'] = array(
                'list'=>'List View',
                'springboard'=>'Springboard');
            break;
        case 'title':
            $item['type'] = 'text';
            $item['subtitle'] = 'The name this module will be presented as to users (i.e. the home screen)';
            break;
        case 'disabled':
            $item['type'] = 'boolean';
            $item['subtitle'] = 'If a module is disabled, it will be inaccessible to all users';
            $item['label'] = 'Module Disabled';
            break;
        case 'disableable':
            $item['type'] = 'boolean';
            $item['subtitle'] = 'Allows users to hide the module from the home screen using the Customize module';
            $item['label'] = 'Users can disable';
            break;
        case 'movable':
            $item['type'] = 'boolean';
            $item['subtitle'] = 'Allows users to adjust the order of this module on the home screen using the Customize module';
            $item['label'] = 'Users can reorder';
            break;
        case 'search':
            $item['type'] = 'boolean';
            $item['subtitle'] = 'Module should be included when doing site-wide (federated) search from the home screen';
            $item['label'] = 'Search';
            break;
        case 'protected':
            $item['type'] = 'boolean';
            $item['subtitle'] = 'Allows access to the module only by authenticated users';
            $item['label'] = 'Protected';
            break;
        case 'secure':
            $item['type'] = 'boolean';
            $item['subtitle'] = 'Module must be accessed using a SSL connection. Note: Maintaing a proper SSL site is the responsibility of the system administrator';
            $item['label'] = 'Secure';
            break;
        case 'id':
            $item['type'] = 'label';
            $item['subtitle'] = 'The internal id for this module. It can only be changed in the source code';
            break;
        default:
            break;
    }
    
    return $item;
  }

                    
  function __construct() {
     $this->moduleName = ucfirst($this->id);
  }
  
  //
  // User functions
  //
  
  public function isLoggedIn()
  {
    $session = $this->getSession();
    return $session->isLoggedIn();
  }
  public function getUser()
  {
    $session = $this->getSession();
    return $session->getUser();
  }

  //
  // Module control functions
  //
  protected function getAllModules() {
    $dirs = array(MODULES_DIR, SITE_DIR . "/modules", THEME_DIR . "/modules");
    $modules = array();
    foreach ($dirs as $dir) {
        if (is_dir($dir)) {
            $d = dir($dir);
            while (false !== ($entry = $d->read())) {
                if ($entry[0]!='.' && is_dir(sprintf("%s/%s", $dir, $entry))) {
                   $module = Module::factory($entry);
                   $modules[$entry] = $module;
                }
            }
        }
    }
    ksort($modules);    
    return $modules;        
  }

  public function getModuleConfig() {
    static $moduleConfig;
    if (!$moduleConfig) {
        $moduleConfig = $this->getConfig($this->id, 'module', ConfigFile::OPTION_CREATE_WITH_DEFAULT);
    }

    return $moduleConfig;
  }

  public function getAPIConfig() {
    static $apiConfig;
    if (!$apiConfig) {
        $apiConfig = $this->getConfig($this->id, 'api', ConfigFile::OPTION_CREATE_WITH_DEFAULT);
    }

    return $apiConfig;
  }

  public function getModuleData() {
    static $moduleData;
    if (!$moduleData) {
        $moduleData = $this->getModuleDefaultData();
        $config = $this->getModuleConfig();
        $moduleData = array_merge($moduleData, $config->getSectionVars(true));
    }
    
    return $moduleData;
  }
  
  public function getAccessControlLists()
  {
    $acls = array();
    $aclStrings = $this->getModuleVar('acl', array(), Config::SUPRESS_ERRORS);
    foreach ($aclStrings as $aclString) {
        if ($acl = AccessControlList::createFromString($aclString)) {
            $acls[] = $acl;
        } else {
            throw new Exception("Invalid ACL $aclString in $this->id");
        }
    }
    
    return $acls;
  }

  //
  // Functions to add inline blocks of text
  // Call these from initializeForPage()
  //
  protected function addInlineCSS($inlineCSS) {
    $this->inlineCSSBlocks[] = $inlineCSS;
  }
  protected function addExternalCSS($url) {
    $this->externalCSSURLs[] = $url;
  }
  protected function addInlineJavascript($inlineJavascript) {
    $this->inlineJavascriptBlocks[] = $inlineJavascript;
  }
  protected function addInlineJavascriptFooter($inlineJavascript) {
    $this->inlineJavascriptFooterBlocks[] = $inlineJavascript;
  }
  protected function addOnOrientationChange($onOrientationChange) {
    $this->onOrientationChangeBlocks[] = $onOrientationChange;
  }
  protected function addOnLoad($onLoad) {
    $this->onLoadBlocks[] = $onLoad;
  }
  protected function addExternalJavascript($url) {
    $this->externalJavascriptURLs[] = $url;
  }
  protected function addJQuery() {
    $this->addExternalJavascript(URL_BASE . 'common/javascript/jquery.js');
  }
  
  //
  // Breadcrumbs
  //
  
  private function encodeBreadcrumbParam($breadcrumbs) {
    return urlencode(gzdeflate(json_encode($breadcrumbs), 9));
  }
  
  private function decodeBreadcrumbParam($breadcrumbs) {
    return json_decode(gzinflate(urldecode($breadcrumbs)), true);
  }
  
  private function loadBreadcrumbs() {
    if ($breadcrumbArg = $this->getArg(MODULE_BREADCRUMB_PARAM)) {
      $breadcrumbs = $this->decodeBreadcrumbParam($breadcrumbArg);
      if (is_array($breadcrumbs)) {
        for ($i = 0; $i < count($breadcrumbs); $i++) {
          $b = $breadcrumbs[$i];
          
          $breadcrumbs[$i]['title'] = $b['t'];
          $breadcrumbs[$i]['longTitle'] = $b['lt'];
          
          $breadcrumbs[$i]['url'] = "{$b['p']}.php";
          if (strlen($b['a'])) {
            $breadcrumbs[$i]['url'] .= "?{$b['a']}";
          }
          
          $linkCrumbs = array_slice($breadcrumbs, 0, $i);
          if (count($linkCrumbs)) { 
            $this->cleanBreadcrumbs(&$linkCrumbs);
            
            $crumbParam = http_build_query(array(
              MODULE_BREADCRUMB_PARAM => $this->encodeBreadcrumbParam($linkCrumbs),
            ));
            if (strlen($crumbParam)) {
              $breadcrumbs[$i]['url'] .= (strlen($b['a']) ? '&' : '?').$crumbParam;
            }
          }
        }

        $this->breadcrumbs = $breadcrumbs;
        
      }
    }
    //error_log(__FUNCTION__."(): loaded breadcrumbs ".print_r($this->breadcrumbs, true));
  }
  
  private function cleanBreadcrumbs(&$breadcrumbs) {
    foreach ($breadcrumbs as $index => $breadcrumb) {
      unset($breadcrumbs[$index]['url']);
      unset($breadcrumbs[$index]['title']);
      unset($breadcrumbs[$index]['longTitle']);
    }
  }
  
  private function getBreadcrumbString($addBreadcrumb=true) {
    $breadcrumbs = $this->breadcrumbs;
    
    $this->cleanBreadcrumbs(&$breadcrumbs);
    
    if ($addBreadcrumb && $this->page != 'index') {
      $args = $this->args;
      unset($args[MODULE_BREADCRUMB_PARAM]);
      
      $breadcrumbs[] = array(
        't'  => $this->breadcrumbTitle,
        'lt' => $this->breadcrumbLongTitle,
        'p'  => $this->page,
        'a'  => http_build_query($args),
      );
    }
    //error_log(__FUNCTION__."(): saving breadcrumbs ".print_r($breadcrumbs, true));
    return $this->encodeBreadcrumbParam($breadcrumbs);
  }
  
  private function getBreadcrumbArgs($addBreadcrumb=true) {
    return array(
      MODULE_BREADCRUMB_PARAM => $this->getBreadcrumbString($addBreadcrumb),
    );
  }

  protected function buildBreadcrumbURL($page, $args, $addBreadcrumb=true) {
    return sprintf("%s?%s",$page, http_build_query(array_merge($args, $this->getBreadcrumbArgs($addBreadcrumb))));
  }
  
  protected function getBreadcrumbArgString($prefix='?', $addBreadcrumb=true) {
    return $prefix.http_build_query($this->getBreadcrumbArgs($addBreadcrumb));
  }

  //
  // Page config
  //
  private function loadPageConfig() {
    if (!isset($this->pageConfig)) {
      $this->setPageTitle($this->moduleName);

      // Load site configuration and help text
      $this->loadSiteConfigFile('strings', false, ConfigFile::OPTION_CREATE_WITH_DEFAULT);
  
      // load module config file
      $pageData = $this->getPageData();

      if (isset($pageData[$this->page])) {
        $pageConfig = $pageData[$this->page];
        
        if (isset($pageConfig['pageTitle'])) {
          $this->pageTitle = $pageConfig['pageTitle'];
        }
          
        if (isset($pageConfig['breadcrumbTitle'])) {
          $this->breadcrumbTitle = $pageConfig['breadcrumbTitle'];
        } else {
          $this->breadcrumbTitle = $this->pageTitle;
        }
          
        if (isset($pageConfig['breadcrumbLongTitle'])) {
          $this->breadcrumbLongTitle = $pageConfig['breadcrumbLongTitle'];
        } else {
          $this->breadcrumbLongTitle = $this->pageTitle;
        }     
        $this->pageConfig = $pageConfig;
      } else {
        $this->pageConfig = array();
      }
    }
  }
  
  protected function setTemplatePage($page, $moduleID=null)
  {
    $moduleID = is_null($moduleID) ? $this->id : $moduleID;
    $this->templatePage = $page;
    $this->templateModule = $moduleID;
  }
  
  
  // Programmatic overrides for titles generated from backend data
  protected function setPage($page) {
    $this->page = $page;
  }
  protected function setPageTitle($title) {
    $this->pageTitle = $title;
  }
  protected function getBreadcrumbTitle() {
    return $this->breadcrumbTitle;
  }
  protected function setBreadcrumbTitle($title) {
    $this->breadcrumbTitle = $title;
  }
  protected function getBreadcrumbLongTitle() {
    return $this->breadcrumbLongTitle;
  }
  protected function setBreadcrumbLongTitle($title) {
    $this->breadcrumbLongTitle = $title;
  }

  //
  // Module debugging
  //
  protected function addModuleDebugString($string) {
    $this->moduleDebugStrings[] = $string;
  }

  //
  // Config files
  //
  
  protected function getPageData()
  {
     $pageConfig = $this->getConfig($this->id, 'nav');
     return $pageConfig->getSectionVars(true);
  }
  
  protected function getConfig($name, $type, $opts=0) {
    $config = ConfigFile::factory($name, $type, $opts);
    $GLOBALS['siteConfig']->addConfig($config);
    return $config;
  }

  protected function loadSiteConfigFile($name, $keyName=null, $opts=0) {
    $config = $this->getConfig($name, 'site', $opts);
    if ($keyName === null) { $keyName = $name; }

    return $this->loadConfigFile($config, $keyName);
  }

  protected function loadWebAppConfigFile($name, $keyName=null, $opts=0) {
    $config = $this->getConfig($name, 'web', $opts);
    if ($keyName === null) { $keyName = $name; }
    return $this->loadConfigFile($config, $keyName);
  }
  
  protected function loadConfigFile(Config $config, $keyName=null) {
    $this->loadTemplateEngineIfNeeded();

    $themeVars = $config->getSectionVars(true);
    
    if ($keyName === false) {
      foreach($themeVars as $key => $value) {
        $this->templateEngine->assign($key, $value);
      }
    } else {
      $this->templateEngine->assign($keyName, $themeVars);
    }
    
    return $themeVars;
  }
  
  //
  // Convenience functions
  //
  public function assignByRef($var, &$value) {
    $this->loadTemplateEngineIfNeeded();
        
    $this->templateEngine->assignByRef($var, $value);
  }
  
  public function assign($var, $value) {
    $this->loadTemplateEngineIfNeeded();
        
    $this->templateEngine->assign($var, $value);
  }
  
  public function getTemplateVars($key) {
    $this->loadTemplateEngineIfNeeded();
    
    return $this->templateEngine->getTemplateVars($key);
  }
  
  //
  // Display page
  //
  public function displayPage() {
    $this->loadTemplateEngineIfNeeded();
        
    $this->loadPageConfig();
    
    // Set variables common to all modules
    $this->assign('moduleID',     $this->id);
    $this->assign('moduleName',   $this->moduleName);
    $this->assign('page',         $this->page);
    $this->assign('isModuleHome', $this->page == 'index');
    
    // Font size for template
    $this->assign('fontsizes',    $this->fontsizes);
    $this->assign('fontsize',     $this->fontsize);
    $this->assign('fontsizeCSS',  $this->getFontSizeCSS());
    $this->assign('fontSizeURLs', $this->getFontSizeURLs());

    // Minify URLs
    $this->assign('minify', $this->getMinifyUrls());
    
    // Google Analytics. This probably needs to be moved
    if ($gaID = $this->getSiteVar('GOOGLE_ANALYTICS_ID', Config::SUPRESS_ERRORS)) {
        $this->assign('GOOGLE_ANALYTICS_ID', $gaID);
        $this->assign('gaImageURL', $this->googleAnalyticsGetImageUrl($gaID));
    }
    
    // Breadcrumbs
    $this->loadBreadcrumbs();
            
    // Set variables for each page
    $this->initializeForPage();

    $this->assign('pageTitle', $this->pageTitle);

    // Variables which may have been modified by the module subclass
    $this->assign('inlineCSSBlocks', $this->inlineCSSBlocks);
    $this->assign('externalCSSURLs', $this->externalCSSURLs);
    $this->assign('inlineJavascriptBlocks', $this->inlineJavascriptBlocks);
    $this->assign('onOrientationChangeBlocks', $this->onOrientationChangeBlocks);
    $this->assign('onLoadBlocks', $this->onLoadBlocks);
    $this->assign('inlineJavascriptFooterBlocks', $this->inlineJavascriptFooterBlocks);
    $this->assign('externalJavascriptURLs', $this->externalJavascriptURLs);

    $this->assign('breadcrumbs',            $this->breadcrumbs);
    $this->assign('breadcrumbArgs',         $this->getBreadcrumbArgs());
    $this->assign('breadcrumbSamePageArgs', $this->getBreadcrumbArgs(false));

    $this->assign('moduleDebugStrings',     $this->moduleDebugStrings);
    
    $moduleStrings = $this->getModuleSection('strings', array(), Config::SUPRESS_ERRORS);
    $this->assign('moduleStrings', $moduleStrings);

    // Module Help
    if ($this->page == 'help') {
      $this->assign('hasHelp', false);
      $template = 'common/'.$this->page;
    } else {
      $this->assign('hasHelp', isset($moduleStrings['help']));
      $template = 'modules/'.$this->templateModule.'/'.$this->templatePage;
    }
    
    // Pager support
    if (isset($this->htmlPager)) {
      $this->assign('pager', $this->getPager());
    }
    
    // Tab support
    if (isset($this->tabbedView)) {
      $this->assign('tabbedView', $this->tabbedView);
    }
    
    // Access Key Start
    $accessKeyStart = count($this->breadcrumbs);
    if ($this->id != 'home') {
      $accessKeyStart++;  // Home link
      if ($this->page != 'index') {
        $accessKeyStart++;  // Module home link
      }
    }
    $this->assign('accessKeyStart', $accessKeyStart);

    if ($this->getSiteVar('AUTHENTICATION_ENABLED')) {
        $this->setCacheMaxAge(0);
        $this->assign('session', $this->getSession());
        $user = $this->getUser();
        $this->assign('session_user', $user);
        if ($authority = $user->getAuthenticationAuthority()) {
            $this->assign('session_authority_image', $authority->getAuthorityImage());
            $this->assign('session_authority_title', $authority->getAuthorityTitle());
        }
    }

    /* set cache age. Modules that present content that rarely changes can set this value
    to something higher */
    header(sprintf("Cache-Control: max-age=%d", $this->cacheMaxAge));
    header("Expires: " . gmdate('D, d M Y H:i:s', time() + $this->cacheMaxAge) . ' GMT');

    // Load template for page
    $this->templateEngine->displayForDevice($template);    
  }
  
  protected function setCacheMaxAge($age)
  {
    $this->cacheMaxAge = intval($age);
  }
  
  
  //
  // Subclass this function to set up variables for each template page
  //
  abstract protected function initializeForPage();

  //
  // Subclass this function to perform initialization just after __construct()
  //
  protected function initialize() {} 
  
  //
  // Subclass these functions for federated search support
  // Return 2 items and a link to get more
  //
  public function federatedSearch($searchTerms, $maxCount, &$results) {
    return 0;
  }
  
  protected function urlForFederatedSearch($searchTerms) {
    return URL_BASE . $this->id . "/". $this->buildBreadcrumbURL('search', array(
      'filter' => $searchTerms));
  }
}