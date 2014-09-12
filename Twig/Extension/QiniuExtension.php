<?php

namespace DWD\QiniuSdkBundle\Twig\Extension;

use DWD\QiniuSdkBundle\Util\Qiniu;

/**
 * Twig extension for the bundle.
 */
class QiniuExtension extends \Twig_Extension
{
    /**
     * @var Qiniu
     *
     * Qiniu Sdk Util
     */
    private $qiniu;

    /**
     * Main constructor
     *
     * @param Qiniu $qiniu Qiniu Sdk Util
     */
    public function __construct( Qiniu $qiniu )
    {
        $this->qiniu = $qiniu;
    }

    /**
     * Getter.
     *
     * @return array
     */
    public function getFilters()
    {
        return array(
            'qiniu_url' => new \Twig_Filter_Method($this, 'getBaseUrl'),
            'qnu'  => new \Twig_Filter_Method($this, 'getBaseUrl'),
        );
    }

    /**
     * Getter.
     *
     * @return array
     */
    public function getFunctions()
    {
        return array(
            'qiniu_url' => new \Twig_Function_Method($this, 'getBaseUrl'),
            'qnu'  => new \Twig_Function_Method($this, 'getBaseUrl'),
        );
    }

    /**
     * Getter.
     *
     * @return string
     */
    public function getBaseUrl( $key )
    {
        $url = call_user_func_array(array($this->qiniu, 'getBaseUrl'), array( $key ));
        return $url;
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'qiniu_extension';
    }
}
