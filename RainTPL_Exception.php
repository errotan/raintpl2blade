<?php

/*
 *  RainTPL
 *  -------
 *  Realized by Federico Ulfo & maintained by the Rain Team
 *  Distributed under the MIT license http://www.opensource.org/licenses/mit-license.php
 *
 *  @version 2.7.2
 */

/**
 * Basic Rain tpl exception.
 */
class RainTPL_Exception extends Exception{
    /**
     * Path of template file with error.
     */
    protected $templateFile = '';

    /**
     * Returns path of template file with error.
     *
     * @return string
     */
    public function getTemplateFile()
    {
        return $this->templateFile;
    }

    /**
     * Sets path of template file with error.
     *
     * @param string $templateFile
     * @return RainTPL_Exception
     */
    public function setTemplateFile($templateFile)
    {
        $this->templateFile = (string) $templateFile;
        return $this;
    }
}
