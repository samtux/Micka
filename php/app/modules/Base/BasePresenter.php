<?php

abstract class BasePresenter extends Nette\Application\UI\Presenter
{
    /** @persistent */
    public $locale;

    /** @var \Kdyby\Translation\Translator @inject */
    public $translator;
    /** @var \Kdyby\Translation\LocaleResolver\SessionResolver  @inject */
    public $translatorSession;
    
    protected $layoutTheme;
    public $appLang;
    public $mickaSession;
    public $langCodes = [];

    public function startup()
	{
		parent::startup();
        
        global $tmp_nbcontext, $tmp_identity, $tmp_appparameters;
        $tmp_nbcontext = $this->context->getByType('Nette\Database\Context');
        $tmp_identity = $this->user;
        $this->context->parameters['appDefaultLocale'] = $this->translator->getDefaultLocale();
        $this->context->parameters['appLocale'] = $this->translator->getLocale();
        //$tmp_appparameters['appUrl'] = $this->link(':Catalog:Default:default');
        $dir = dirname($this->getReflection()->getFileName());
        $this->layoutTheme = file_exists("$dir/templates/" . $this->context->parameters['app']['layoutTheme'] . "/Default/default.latte")
            ? $this->context->parameters['app']['layoutTheme']
            : 'default';
        $this->langCodes = $this->context->parameters['langCodes'];
        $this->appLang = isset($this->langCodes[$this->translator->getLocale()])
            ? $this->langCodes[$this->translator->getLocale()]
            : substr($this->context->parameters['app']['langs'],0,3);
        $this->mickaSession = $this->getSession('mickaSection');
        
        define("CSW_LOG", __DIR__ . '/../../../log');
        define("MICKA_ADMIN_IP", '');
        define("MICKA_LANG", $this->appLang);
        define("DB_DRIVER", 'postgre');
        define("MAXRECORDS", 10);
        define("LIMITMAXRECORDS", 100);
        define("CATCLIENT_PATH", '');
        define("PHPPRG_DIR", '');
        define("REWRITE_MODE", TRUE);
        define("WMS_CLIENT", '');
        define("MICKA_THEME", 'default');

        $url = $this->context->getByType('Nette\Http\Request')->getUrl();
        $locale = $this->translator->getDefaultLocale() == $this->translator->getLocale()
            ? ''
            : '/' . $this->translator->getLocale();
        define("MICKA_URL", $url->hostUrl . rtrim($url->basePath,'/') . $locale);
        define("CSW_URL", $url->hostUrl . rtrim($url->basePath,'/') . '/csw');
        if (isset($this->context->parameters['app']['proxy']) && $this->context->parameters['app']['proxy'] != '') {
            define("CONNECTION_PROXY", $this->context->parameters['app']['proxy']);
        } 

        $this->context->parameters['hostUrl'] = isset($this->context->parameters['app']['mickaUrl']) && $this->context->parameters['app']['mickaUrl'] != ''
            ? $this->context->parameters['app']['mickaUrl']
            : $url->hostUrl;
        $this->context->parameters['basePath'] = rtrim($url->basePath,'/');
        $this->context->parameters['locale'] = $locale;
        $this->context->parameters['cswUrl'] = strpos($url->path, '/filter/') === false
            ? $this->context->parameters['hostUrl'] . $this->context->parameters['basePath'] . '/csw/'
            : $this->context->parameters['hostUrl'] . $url->path  . '/';
        $tmp_appparameters = $this->context->parameters;

        define("CSW_TIMEOUT", 30);
        define("HTTP_XML", "Content-type: application/xml; charset=utf-8");
        define("HTTP_SOAP", "Content-type: application/soap+xml; charset=utf-8"); //TODO ověřit
        define("HTTP_JSON", "Content-type: application/json; charset=utf-8");
        define("HTTP_HTML", "Content-type: text/html; charset=utf-8");
        define("HTTP_CSV", "Content-type: text/csv; charset=utf-8");
        define("HTTP_KML", "Content-type: application/vnd.google-earth.kml+xml");
        define("HTTP_CORS", "Access-Control-Allow-Origin: *"); // TODO do konfigurace

        define("XML_HEADER", '<?xml version="1.0" encoding="UTF-8"?'.'>');
        define("SOAP_HEADER", '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope"><soap:Body>');
        define("SOAP_FOOTER", '</soap:Body></soap:Envelope>');
        define("CSW_MAXFILESIZE", 5000000);
	}
    
    /**
     * Check authorization
     * @return void
     */
    public function checkRequirements($element)
    {
        if ($element instanceof Nette\Application\UI\MethodReflection) {
            // Check permissions for Action/handle methods
            $resource = Nette\Reflection\Method::from($this->presenter, $element->name)->getAnnotation('resource');
        } else {
            // Check permissions for presenter access
            $resource = Nette\Reflection\ClassType::from($this->presenter)->getAnnotation('resource');
        }
        if (!$this->user->isAllowed($resource)) {
            throw new Nette\Application\ForbiddenRequestException;
        }
    }
    
    /** @resource Catalog:Guest */
    public function handleChangeLocale($locale)
    {
        $this->translatorSession->setLocale($locale);
        $this->redirect('this');
    }

	public function beforeRender()
	{
        $this->template->parameters = $this->context->parameters;
        $this->template->themePath = '/layout/' . $this->layoutTheme;
        $this->template->extjsPath = '/wwwlibs/ext/ext-4.2';
        $this->template->appLang = $this->appLang;
        $this->template->appLang2 = $this->translator->getLocale();
        $this->template->action = $this->action;
        $this->template->navigation = [];
        $this->template->hs_initext = '';
        $this->template->langCodes = $this->langCodes;
        $this->template->pageTitle = isset($this->context->parameters['app']['pageTitle'][$this->appLang]) && $this->context->parameters['app']['pageTitle'][$this->appLang] != ''
            ? $this->context->parameters['app']['pageTitle'][$this->appLang]
            : 'openMicka';
	}

    /**
     * Formats layout template file names.
     * @return array
     */
    public function formatLayoutTemplateFiles()
    {
        $name = $this->getName();
        $presenter = substr($name, strrpos(':' . $name, ':'));
        $dir = dirname($this->getReflection()->getFileName());
        return array(
            "$dir/templates/$this->layoutTheme/$presenter/@layout.latte",
            "$dir/templates/$this->layoutTheme/@layout.latte"
        );
    }

    /**
     * Formats view template file names.
     * @return array
     */
    public function formatTemplateFiles()
    {

        $name = $this->getName();
        $presenter = substr($name, strrpos(':' . $name, ':'));
        $dir = dirname($this->getReflection()->getFileName());
        return array(
            "$dir/templates/$this->layoutTheme/$presenter/$this->view.latte"
        );

    }
}
