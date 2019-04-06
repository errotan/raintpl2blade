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
 * Exception thrown when syntax error occurs.
 */
class RainTPL_SyntaxException extends RainTPL_Exception{
    /**
     * Line in template file where error has occured.
     *
     * @var int | null
     */
    protected $templateLine = null;

    /**
     * Tag which caused an error.
     *
     * @var string | null
     */
    protected $tag = null;

    /**
     * Returns line in template file where error has occured
     * or null if line is not defined.
     *
     * @return int | null
     */
    public function getTemplateLine()
    {
        return $this->templateLine;
    }

    /**
     * Sets  line in template file where error has occured.
     *
     * @param int $templateLine
     * @return RainTPL_SyntaxException
     */
    public function setTemplateLine($templateLine)
    {
        $this->templateLine = (int) $templateLine;
        return $this;
    }

    /**
     * Returns tag which caused an error.
     *
     * @return string
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * Sets tag which caused an error.
     *
     * @param string $tag
     * @return RainTPL_SyntaxException
     */
    public function setTag($tag)
    {
        $this->tag = (string) $tag;
        return $this;
    }
}
