<?php
/**
 * Copyright © Wubinworks. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Wubinworks\Translation\Plugin;

class TranslationJsDataProviderPlugin
{
    /**
     * @var \Wubinworks\Translation\Model\TranslateInterface
     */
    private $translate;

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
     * @param \Magento\Framework\Registry $registry
     * @param array $pathList
     */
    public function __construct(
        \Wubinworks\Translation\Model\TranslateInterface $translate,
        \Magento\Framework\Registry $registry,
        array $pathList = []
    ) {
        $this->translate = $translate;
        $this->registry = $registry;
        $this->pathList = $pathList;
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
     * Force specified translation data go to js-translation.json
     *
     * @param \Magento\Translation\Model\Js\DataProviderInterface $subject
     * @param array $result
     * @param string $themePath
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetData(
        \Magento\Translation\Model\Js\DataProviderInterface $subject,
        $result,
        $themePath
    ): array {
        if(empty($this->pathList)) {
            return $result;
        }

        $locale = null;
        $area = null;
        $forceReload = false;
        $magentoTranslateParams = $this->registry->registry('magento_translate_params');
        if($magentoTranslateParams) {
            $locale = $magentoTranslateParams->getData('locale');
            $area = $magentoTranslateParams->getData('area');
            $forceReload = $magentoTranslateParams->getData('force_reload');
        }

        $this->sortPathList('desc');
        $dictionary = $result;
        foreach($this->pathList as $name => $pathData) {
            if(!$pathData['disabled']) {
                $dictionary = array_merge($dictionary, $this->translate->getData($pathData['path'], $locale, $area, $forceReload));
            }
        }

        ksort($dictionary);
        return $dictionary;
    }
}
