<?php
/**
 * Copyright © Wubinworks. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Wubinworks\Translation\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Filesystem\DriverInterface;

/**
 * Wubinworks Custom Translate library
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class Translate implements \Wubinworks\Translation\Model\TranslateInterface
{
    public const CONFIG_AREA_KEY = 'area';
    public const CONFIG_LOCALE_KEY = 'locale';
    public const CONFIG_SCOPE_KEY = 'scope';
    public const CONFIG_THEME_KEY = 'theme';
    public const CONFIG_MODULE_KEY = 'module';

    /**
     * @var string
     */
    protected $_localeCode;

    /**
     * Translator configuration array
     *
     * @var array
     */
    protected $_config;

    /**
     * Cache identifier
     *
     * @var string
     */
    protected $_cacheId;

    /**
     * Translation data
     *
     * @var []
     */
    protected $_data = [];

    /**
     * @var \Magento\Framework\View\DesignInterface
     */
    protected $_viewDesign;

    /**
     * @var \Magento\Framework\Cache\FrontendInterface
     */
    protected $_cache;

    /**
     * @var \Magento\Framework\View\FileSystem
     */
    protected $_viewFileSystem;

    /**
     * @var \Magento\Framework\Module\ModuleList
     */
    protected $_moduleList;

    /**
     * @var \Magento\Framework\Module\Dir\Reader
     */
    protected $_modulesReader;

    /**
     * @var \Magento\Framework\App\ScopeResolverInterface
     */
    protected $_scopeResolver;

    /**
     * @var \Magento\Framework\Translate\ResourceInterface
     */
    protected $_translateResource;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $_locale;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $_appState;

    /**
     * @var \Magento\Framework\Filesystem\Directory\Read
     */
    protected $directory;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @var \Magento\Framework\File\Csv
     */
    protected $_csvParser;

    /**
     * @var \Magento\Framework\App\Language\Dictionary
     */
    protected $packDictionary;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;

    /**
     * @var DriverInterface
     */
    private $fileDriver;

    /**
     * @param \Magento\Framework\View\DesignInterface $viewDesign
     * @param \Magento\Framework\Cache\FrontendInterface $cache
     * @param \Magento\Framework\View\FileSystem $viewFileSystem
     * @param \Magento\Framework\Module\ModuleList $moduleList
     * @param \Magento\Framework\Module\Dir\Reader $modulesReader
     * @param \Magento\Framework\App\ScopeResolverInterface $scopeResolver
     * @param \Magento\Framework\Translate\ResourceInterface $translate
     * @param \Magento\Framework\Locale\ResolverInterface $locale
     * @param \Magento\Framework\App\State $appState
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\File\Csv $csvParser
     * @param \Magento\Framework\App\Language\Dictionary $packDictionary
     * @param DriverInterface|null $fileDriver
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\View\DesignInterface $viewDesign,
        \Magento\Framework\Cache\FrontendInterface $cache,
        \Magento\Framework\View\FileSystem $viewFileSystem,
        \Magento\Framework\Module\ModuleList $moduleList,
        \Magento\Framework\Module\Dir\Reader $modulesReader,
        \Magento\Framework\App\ScopeResolverInterface $scopeResolver,
        \Magento\Framework\Translate\ResourceInterface $translate,
        \Magento\Framework\Locale\ResolverInterface $locale,
        \Magento\Framework\App\State $appState,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\File\Csv $csvParser,
        \Magento\Framework\App\Language\Dictionary $packDictionary,
        DriverInterface $fileDriver = null
    ) {
        $this->_viewDesign = $viewDesign;
        $this->_cache = $cache;
        $this->_viewFileSystem = $viewFileSystem;
        $this->_moduleList = $moduleList;
        $this->_modulesReader = $modulesReader;
        $this->_scopeResolver = $scopeResolver;
        $this->_translateResource = $translate;
        $this->_locale = $locale;
        $this->_appState = $appState;
        $this->request = $request;
        $this->directory = $filesystem->getDirectoryRead(DirectoryList::ROOT);
        $this->_csvParser = $csvParser;
        $this->packDictionary = $packDictionary;
        $this->fileDriver = $fileDriver
            ?? ObjectManager::getInstance()->get(File::class);

        $this->_config = [
            self::CONFIG_AREA_KEY => null,
            self::CONFIG_LOCALE_KEY => null,
            self::CONFIG_SCOPE_KEY => null,
            self::CONFIG_THEME_KEY => null,
            self::CONFIG_MODULE_KEY => null,
        ];
    }

    /**
     * Load translation data
     *
     * @param string $path
     * @param string|null $locale
     * @param string|null $area
     * @param bool $forceReload
     * @return $this
     */
    protected function loadData(string $path, $locale, $area, $forceReload)
    {
        $this->_data = [];
        $path = trim($path, '/');
        $this->setLocale($locale);
        if ($area === null) {
            $area = $this->_appState->getAreaCode();
        }
        $this->setConfig(
            [
                self::CONFIG_AREA_KEY => $area,
            ]
        );

        if (!$forceReload) {
            $data = $this->_loadCache(strtr($path, '/', '_'));
            if (false !== $data) {
                $this->_data = $data;
                return $this;
            }
        }

        $this->_loadModuleTranslation($path);
        $this->_loadThemeTranslation($path);

        if (!$forceReload) {
            $this->_saveCache(strtr($path, '/', '_'));
        }

        return $this;
    }

    /**
     * Initialize configuration
     *
     * @param   array $config
     * @return  $this
     */
    protected function setConfig($config)
    {
        $this->_config = $config;
        if (!isset($this->_config[self::CONFIG_LOCALE_KEY])) {
            $this->_config[self::CONFIG_LOCALE_KEY] = $this->getLocale();
        }
        if (!isset($this->_config[self::CONFIG_SCOPE_KEY])) {
            $this->_config[self::CONFIG_SCOPE_KEY] = $this->getScope();
        }
        if (!isset($this->_config[self::CONFIG_THEME_KEY])) {
            $this->_config[self::CONFIG_THEME_KEY] = $this->_viewDesign->getDesignTheme()->getThemePath();
        }
        if (!isset($this->_config[self::CONFIG_MODULE_KEY])) {
            $this->_config[self::CONFIG_MODULE_KEY] = $this->getControllerModuleName();
        }
        return $this;
    }

    /**
     * Retrieve scope code
     *
     * @return string
     */
    protected function getScope()
    {
        $scope = ($this->getConfig(self::CONFIG_AREA_KEY) === 'adminhtml') ? 'admin' : null;
        return $this->_scopeResolver->getScope($scope)->getCode();
    }

    /**
     * Retrieve config value by key
     *
     * @param   string $key
     * @return  mixed
     */
    protected function getConfig($key)
    {
        if (isset($this->_config[$key])) {
            return $this->_config[$key];
        }
        return null;
    }

    /**
     * Retrieve name of the current module
     *
     * @return mixed
     */
    protected function getControllerModuleName()
    {
        return $this->request->getControllerModule();
    }

    /**
     * Load data from module translation files
     *
     * @param string $path
     * @return $this
     */
    protected function _loadModuleTranslation(string $path)
    {
        $currentModule = $this->getControllerModuleName();
        $allModulesExceptCurrent = array_diff($this->_moduleList->getNames(), [$currentModule]);

        $this->loadModuleTranslationByModulesList($allModulesExceptCurrent, $path);
        $this->loadModuleTranslationByModulesList([$currentModule], $path);
        return $this;
    }

    /**
     * Load data from module translation files by list of modules
     *
     * @param array $modules
     * @param string $path
     * @return $this
     */
    protected function loadModuleTranslationByModulesList(array $modules, string $path)
    {
        foreach ($modules as $module) {
            $moduleFilePath = $this->_getModuleTranslationFile($module, $this->getLocale(), $path);
            $this->_addData($this->_getFileData($moduleFilePath));
        }
        return $this;
    }

    /**
     * Adding translation data
     *
     * @param array $data
     * @return $this
     */
    protected function _addData($data)
    {
        foreach ($data as $key => $value) {
            if ($key === $value) {
                if (isset($this->_data[$key])) {
                    unset($this->_data[$key]);
                }
                continue;
            }

            $key = is_array($key) ? $key : (string) $key;
            $value = is_array($value) ? $value : (string) $value;
            $key = str_replace('""', '"', $key);
            $value = str_replace('""', '"', $value);

            $this->_data[$key] = $value;
        }
        return $this;
    }

    /**
     * Load current theme translation according to fallback
     *
     * @param string $path
     * @return $this
     */
    protected function _loadThemeTranslation(string $path)
    {
        $themeFiles = $this->getThemeTranslationFilesList($this->getLocale(), $path);

        /** @var string $file */
        foreach ($themeFiles as $file) {
            if ($file) {
                $this->_addData($this->_getFileData($file));
            }
        }

        return $this;
    }

    /**
     * Load translation dictionary from language packages
     *
     * @return void
     */
    protected function _loadPackTranslation()
    {
        $data = $this->packDictionary->getDictionary($this->getLocale());
        $this->_addData($data);
    }

    /**
     * Loading current translation from DB
     *
     * @return $this
     */
    protected function _loadDbTranslation()
    {
        $data = $this->_translateResource->getTranslationArray(null, $this->getLocale());
        $this->_addData(array_map('htmlspecialchars_decode', $data));
        return $this;
    }

    /**
     * Retrieve translation file for module
     *
     * @param string $moduleName
     * @param string $locale
     * @param string $path
     * @return string
     */
    protected function _getModuleTranslationFile($moduleName, $locale, string $path)
    {
        $file = $this->_modulesReader->getModuleDir(\Magento\Framework\Module\Dir::MODULE_I18N_DIR, $moduleName);
        $file .= '/';
        $file .= ltrim($path . '/' . $locale . '.csv', '/');
        return $file;
    }

    /**
     * Get theme translation locale file name
     *
     * @param string|null $locale
     * @param array $config
     * @param string $path
     * @return string|null
     */
    private function getThemeTranslationFileName(?string $locale, array $config, string $path): ?string
    {
        $fileName = $this->_viewFileSystem->getLocaleFileName(
            'i18n' . '/' . ltrim($path . '/' . $locale . '.csv', '/'),
            $config
        );

        return $fileName ? $fileName : null;
    }

    /**
     * Get parent themes for the current theme in fallback order
     *
     * @return array
     */
    private function getParentThemesList(): array
    {
        $themes = [];

        $parentTheme = $this->_viewDesign->getDesignTheme()->getParentTheme();
        while ($parentTheme) {
            $themes[] = $parentTheme;
            $parentTheme = $parentTheme->getParentTheme();
        }
        $themes = array_reverse($themes);

        return $themes;
    }

    /**
     * Retrieve translation files for themes according to fallback
     *
     * @param string $locale
     * @param string $path
     * @return array
     */
    private function getThemeTranslationFilesList($locale, string $path): array
    {
        $translationFiles = [];

        /** @var \Magento\Framework\View\Design\ThemeInterface $theme */
        foreach ($this->getParentThemesList() as $theme) {
            $config = $this->_config;
            $config['theme'] = $theme->getCode();
            $translationFiles[] = $this->getThemeTranslationFileName($locale, $config, $path);
        }

        $translationFiles[] = $this->getThemeTranslationFileName($locale, $this->_config, $path);

        return $translationFiles;
    }

    /**
     * Not used
     * Retrieve translation file for theme
     *
     * @param string $locale
     * @return string
     *
     * @deprecated 102.0.1
     *
     * @see \Magento\Framework\Translate::getThemeTranslationFilesList
     */
    protected function _getThemeTranslationFile($locale)
    {
        return $this->_viewFileSystem->getLocaleFileName(
            'i18n' . '/' . $locale . '.csv',
            $this->_config
        );
    }

    /**
     * Retrieve data from file
     *
     * @param string $file
     * @return array
     */
    protected function _getFileData($file)
    {
        $data = [];
        if ($this->fileDriver->isExists($file)) {
            $this->_csvParser->setDelimiter(',');
            $data = $this->_csvParser->getDataPairs($file);
        }
        return $data;
    }

    /**
     * Load and return translation data
     *
     * @param string $path
     * @param string|null $locale
     * @param string|null $area
     * @param bool $forceReload
     * @return array
     */
    public function getData(string $path = '', $locale = null, $area = null, $forceReload = false)
    {
        $this->_data = [];
        $this->loadData($path, $locale, $area, $forceReload);
        return $this->_data;
    }

    /**
     * Retrieve locale
     *
     * @return string
     */
    public function getLocale()
    {
        if (null === $this->_localeCode) {
            $this->_localeCode = $this->_locale->getLocale();
        }
        return $this->_localeCode;
    }

    /**
     * Set locale
     *
     * @param string $locale
     * @return \Magento\Framework\TranslateInterface
     */
    public function setLocale($locale)
    {
        $this->_localeCode = $locale;
        $this->_config[self::CONFIG_LOCALE_KEY] = $locale;
        return $this;
    }

    /**
     * Retrieve theme code
     *
     * @return string
     */
    public function getTheme()
    {
        $theme = $this->request->getParam(self::CONFIG_THEME_KEY);
        if (empty($theme)) {
            return self::CONFIG_THEME_KEY . $this->getConfig(self::CONFIG_THEME_KEY);
        }
        return self::CONFIG_THEME_KEY . $theme['theme_title'];
    }

    /**
     * Retrieve cache identifier. Used by this class
     *
     * @param string $path
     * @return string
     */
    protected function getCacheId(string $path)
    {
        $this->_cacheId = $this->getCacheIdByPath($path, $this->_config);
        return $this->_cacheId;
    }

    /**
     * Get Cache Identifier by Path, if $path is '', return Magento default cache identifier.
     * Can be used for debugging purpose.
     *
     * @param string $path
     * @param array $config
     * @return string
     */
    public function getCacheIdByPath(string $path = '', array $config = [])
    {
        if (!isset($config[self::CONFIG_AREA_KEY])) {
            $config[self::CONFIG_AREA_KEY] = $this->_appState->getAreaCode();
        } else {
            $config[self::CONFIG_AREA_KEY] = (string)$config[self::CONFIG_AREA_KEY];
        }
        $this->setConfig($config);

        if(strlen($path)) {
            $cacheId = $this->getSelfModuleName() . '_' . $path;
        } else {
            $cacheId = \Magento\Framework\App\Cache\Type\Translate::TYPE_IDENTIFIER;
        }
        $cacheId .= '_' . $this->_config[self::CONFIG_LOCALE_KEY];
        $cacheId .= '_' . $this->_config[self::CONFIG_AREA_KEY];
        $cacheId .= '_' . $this->_config[self::CONFIG_SCOPE_KEY];
        $cacheId .= '_' . $this->_config[self::CONFIG_THEME_KEY];
        $cacheId .= '_' . $this->_config[self::CONFIG_MODULE_KEY];

        return $cacheId;
    }

    /**
     * Extract module name from current namespace
     *
     * @return string
     */
    protected function getSelfModuleName()
    {
        return implode('_', array_slice(explode('\\', ltrim(__CLASS__)), 0, 2));
    }

    /**
     * Loading data cache
     *
     * @param string $path
     * @return array|bool
     */
    protected function _loadCache(string $path)
    {
        $data = $this->_cache->load($this->getCacheId($path));
        if ($data) {
            $data = $this->getSerializer()->unserialize($data);
        }
        return $data;
    }

    /**
     * Saving data cache
     * Use the same cache tag as \Magento\Framework\App\Cache\Type\Translate::TYPE_IDENTIFIER, so the cache having a "path" will be cleaned togetther with default translation cache.
     * @see \Magento\Framework\Cache\Frontend\Decorator\TagScope::save
     * @param string $path
     * @return $this
     */
    protected function _saveCache(string $path)
    {
        if ($this->_data == null) {
            $this->_data = [];
        }
        $this->_cache->save($this->getSerializer()->serialize($this->_data), $this->getCacheId($path), [], false);
        return $this;
    }

    /**
     * Get serializer
     *
     * @return \Magento\Framework\Serialize\SerializerInterface
     * @deprecated 101.0.0
     * @see we don't recommend this approach anymore
     */
    private function getSerializer()
    {
        if ($this->serializer === null) {
            $this->serializer = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Framework\Serialize\SerializerInterface::class);
        }
        return $this->serializer;
    }

    /**
     * Compatibility issue.
     * This class needs to implement \Magento\Framework\ObjectManager\ResetAfterRequestInterface
     * The above interface was added since 2.4.6? 2.4.7?
     *
     * Resets mutable state and/or resources in objects that need to be cleaned after a response has been sent.
     *
     * @return void
     */
    public function _resetState(): void
    {
        $this->_config = [];
        $this->_data = [];
        $this->_localeCode = null;
    }
}
