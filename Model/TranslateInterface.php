<?php
/**
 * Copyright © Wubinworks. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Wubinworks\Translation\Model;

interface TranslateInterface
{
    /**
     * Retrieve translation data
     *
     * @param string $path
     * @param string|null $locale
     * @param string|null $area
     * @param bool $forceReload
     * @return array
     */
    public function getData(string $path = '', $locale = null, $area = null, $forceReload = false);

    /**
     * Retrieve locale
     *
     * @return string
     */
    public function getLocale();

    /**
     * Set locale
     *
     * @param string $locale
     * @return \Magento\Framework\TranslateInterface
     */
    public function setLocale($locale);

    /**
     * Retrieve theme code
     *
     * @return string
     */
    public function getTheme();
}
