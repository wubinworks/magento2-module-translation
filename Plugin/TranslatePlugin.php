<?php
/**
 * Copyright © Wubinworks. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Wubinworks\Translation\Plugin;

class TranslatePlugin
{
    /**
     * @var \Wubinworks\Translation\Model\TranslateInterface
     */
    private $translate;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var \Magento\Framework\Cache\FrontendInterface
     */
    protected $cache;

    /**
     * @var string
     */
    protected $cacheType;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;

    /**
     * @var \Magento\Framework\Registry
     */
    private $registry;

    /**
     * @var array
     */
    protected $pathList;

    /**
     * Constructor
     *
     * @param \Wubinworks\Translation\Model\TranslateInterface $translate
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Magento\Framework\Registry $registry
     * @param string $cacheType
     * @param array $pathList
     */
    public function __construct(
        \Wubinworks\Translation\Model\TranslateInterface $translate,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \Magento\Framework\Registry $registry,
        string $cacheType = 'Magento\Framework\App\Cache\Type\Translate',
        array $pathList = []
    ) {
        $this->translate = $translate;
        $this->objectManager = $objectManager;
        $this->serializer = $serializer;
        $this->registry = $registry;
        $this->registry->register('magento_translate_params', new \Magento\Framework\DataObject([
            'locale' => null,
            'area' => null,
            'force_reload' => false
        ]));
        $this->cacheType = $cacheType;
        $this->pathList = $pathList;
    }

    /**
     * Get cache instance model
     *
     * @return \Magento\Framework\Cache\FrontendInterface
     * @throws \UnexpectedValueException
     */
    protected function getCache()
    {
        if(!isset($this->cache)) {
            $this->cache = $this->objectManager->get($this->cacheType);
        }
        if (!$this->cache instanceof \Magento\Framework\Cache\FrontendInterface) {
            throw new \UnexpectedValueException("Cache type class '{$this->cacheType}' has to be a cache frontend.");
        }

        return $this->cache;
    }

    /**
     * Record loadData arguments
     *
     * @param \Magento\Framework\TranslateInterface $subject
     * @param string|null $area
     * @param bool $forceReload
     * @return array
     */
    public function beforeLoadData(
        \Magento\Framework\TranslateInterface $subject,
        $area = null,
        $forceReload = false
    ): array {
        $magentoTranslateParams = $this->registry->registry('magento_translate_params');
        $magentoTranslateParams->setData('area', $area);
        $magentoTranslateParams->setData('force_reload', $forceReload);
        return [$area, $forceReload];
    }

    /**
     * Record setLocale argument
     *
     * @param \Magento\Framework\TranslateInterface $subject
     * @param string $locale
     * @return array
     */
    public function beforeSetLocale(
        \Magento\Framework\TranslateInterface $subject,
        $locale
    ): array {
        $magentoTranslateParams = $this->registry->registry('magento_translate_params');
        $magentoTranslateParams->setData('locale', $locale);
        return [$locale];
    }

    /**
     * Sort path list
     *
     * @param string $order
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function sortPathList(string $order = 'desc'): void
    {
        uasort($this->pathList, function ($a, $b) use ($order) {
            $a = (int)$a['sortOrder'];
            $b = (int)$b['sortOrder'];
            if ($a == $b) {
                return 0;
            }
            if($order == 'desc') {
                return ($a > $b) ? -1 : 1;
            } elseif($order == 'asc') {
                return ($a < $b) ? -1 : 1;
            } else {
                throw new \InvalidArgumentException("Unknown sort order '{$order}'.");
            }
        });
    }

    /**
     * Override translation data
     *
     * @param \Magento\Framework\TranslateInterface $subject
     * @return array
     */
    public function afterGetData(
        \Magento\Framework\TranslateInterface $subject,
        $result
    ): array {
        if(empty($this->pathList)) {
            return $result;
        }

        $magentoTranslateParams = $this->registry->registry('magento_translate_params');
        // test if magento default translation cache exists
        if(!$magentoTranslateParams->getData('force_reload') && $this->getCache()->test($this->translate->getCacheIdByPath())) {
            // return cache
            return $this->serializer->unserialize($this->getCache()->load($this->translate->getCacheIdByPath()));
        }

        $this->sortPathList('desc');
        foreach($this->pathList as $name => $pathData) {
            if(!$pathData['disabled']) {
                $result = array_merge(
                    $result,
                    $this->translate->getData(
                        $pathData['path'],
                        $magentoTranslateParams->getData('locale'),
                        $magentoTranslateParams->getData('area'),
                        $magentoTranslateParams->getData('force_reload')
                    )
                );
            }
        }

        return $result;
    }
}
