<?php

/*
 * register qiniu php sdk as a service into symfony 2
 *
 * you can get it with $this->container->get("dwd_qiniu_sdk");
 *
 * see more qiniu php-sdk, read: https://github.com/qiniu/php-sdk/tree/develop/docs
 *
 * error codes table: http://developer.qiniu.com/docs/v6/api/reference/codes.html
 *
 */

namespace DWD\QiniuSdkBundle\Util;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use DWD\QiniuSdkBundle\Exception\QiniuPutException;
use DWD\QiniuSdkBundle\Exception\QiniuDeleteException;
use DWD\QiniuSdkBundle\Exception\QiniuMoveException;
use DWD\QiniuSdkBundle\Exception\QiniuCopyException;
use DWD\QiniuSdkBundle\Exception\QiniuStatException;

class Qiniu
{
    private $accessKey;
    private $secretKey;
    private $bucket;
    private $domain;
    private $putPolicy = null;
    public $client;

    /*
     * you need add some parameters in your app/config/parameters.yml.dist
     *
     * ...
     *
     * qiniu_sdk_accessKey: <YOUR_ACCESS_KEY>
     * qiniu_sdk_secretKey: <YOUR_SECRET_KEY>
     * qiniu_sdk_bucket: <YOUR_BUCKET_NAME>
     * qiniu_sdk_domain: <YOUR_BUCKET_DOMAIN>
     *
     * then run composer update and follow the tip and enter your qiniu config
     *
     */
    public function __construct( ContainerInterface $container, $accessKey, $secretKey, $bucket, $domain )
    {
        $this->container = $container;
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->bucket = $bucket;
        $this->domain = $domain;

        Qiniu_SetKeys( $this->accessKey, $this->secretKey );

        $this->client = new \Qiniu_MacHttpClient(null);
    }

    /*
     * get current bucket name
     */
    public function getBucket()
    {
        return $this->bucket;
    }

    /*
     * set current bucket name
     */
    public function setBucket( $bucket )
    {
        $this->bucket = $bucket;

        return $this;
    }

    /*
     * get resource url with qiniu key
     */
    public function getBaseUrl( $key )
    {
        return Qiniu_RS_MakeBaseUrl( $this->domain, $key );
    }

    /*
     * get upload token
     *
     * read more: https://github.com/qiniu/php-sdk/tree/develop/docs#12-%E4%B8%8A%E4%BC%A0%E7%AD%96%E7%95%A5
     */
    public function getUpToken( $expire = 1800, $callbackUrl = null, $callbackBody = null )
    {
        if( $this->putPolicy === null ) {
            $this->putPolicy = new \Qiniu_RS_PutPolicy( $this->bucket );
        }

        /*
         * set expires time
         * $expire second
         */
        if( $expire != $this->putPolicy->Expires ) {
            $this->putPolicy->Expires = $expire;
        }

        if( $callbackUrl != $this->putPolicy->CallbackUrl ) {
            /*
             * TODO 检查callbackUrl是否为其他站点
             */
            $this->putPolicy->CallbackUrl = $callbackUrl;
        }

        if( $callbackBody != $this->putPolicy->CallbackBody ) {
            $this->putPolicy->CallbackBody = $callbackBody;
        }

        return $this->putPolicy->Token(null);
    }

    /*
     * upload a text to qiniu
     *
     * read: https://github.com/qiniu/php-sdk/tree/develop/docs#11%E4%B8%8A%E4%BC%A0%E6%B5%81%E7%A8%8B
     */
    public function put( $content )
    {
        $key = md5($content);

        $upToken = $this->getUpToken();

        $i = 0;

        do {
            list($ret, $err) = Qiniu_Put( $upToken, $key, $content, null );
            $i++;

            /*
             * 503 服务端不可用
             * 504 服务端操作超时
             * 599 服务端操作失败
             */
        } while( $i < 3 AND $err !== null AND in_array( $err->Code, array( 503, 504, 599 ) ) );

        if( $err !== null ) {
            throw new QiniuPutException( $err->Err, $err->Code, $key );
        } else {
            return $ret['key'];
        }
    }

    /*
     * upload a file to qiniu
     *
     * read: https://github.com/qiniu/php-sdk/tree/develop/docs#11%E4%B8%8A%E4%BC%A0%E6%B5%81%E7%A8%8B
     */
    public function putFile( $filePath )
    {
        if( !file_exists( $filePath ) ) {
            throw new FileNotFoundException($filePath, 404);
        }

        $key = md5_file( $filePath );

        $upToken = $this->getUpToken();

        $putExtra = new \Qiniu_PutExtra();
        $putExtra->Crc32 = 1;

	$i = 0;
        do {
            list($ret, $err) = Qiniu_PutFile( $upToken, $key, $filePath, $putExtra );
            $i++;

            /*
             * 503 服务端不可用
             * 504 服务端操作超时
             * 599 服务端操作失败
             */
        } while( $i < 3 AND $err !== null AND in_array( $err->Code, array( 503, 504, 599 ) ) );

        if( $err !== null ) {
            throw new QiniuPutException( $err->Err, $err->Code, $key );
        } else {
            return $ret['key'];
        }
    }

    /*
     * delete a resource from qiniu
     */
    public function delete( $key )
    {
        $err = Qiniu_RS_Delete( $this->client, $this->bucket, $key );

        if( $err !== null ) {
            throw new QiniuDeleteException( $err->Err, $err->Code, $key );
        } else {
            return true;
        }
    }

    /*
     * batch delete
     */
    public function batchDelete()
    {
        $keys = func_get_args();

        if( func_num_args() <= 0 ) {
            return array();
        }

        foreach( $keys as $i => $key ) {
            $keys[$i] = new \Qiniu_RS_EntryPath( $this->bucket, $key );
        }

        list( $ret, $err ) = Qiniu_RS_BatchDelete($client, $entries);

        if( $err !== null ) {
            $ret['code'] = $err->Code;
            return $ret;
        } else {
            return $ret;
        }
    }

    /*
     * rename a resource
     */
    public function move( $oldKey, $newKey, $newBucket = null )
    {
        if( $newBucket === null ) {
            $newBucket = $this->bucket;
        }

        $err = Qiniu_RS_Move( $this->client, $this->bucket, $oldKey, $newBucket, $newKey );

        if( $err !== null ) {
            throw new QiniuMoveException( $err->Err, $err->Code, $key );
        } else {
            return true;
        }
    }

    /*
     * batch rename
     *
     * param $pair array( 'old' => 'old-key', 'new' => 'new-key' )
     * param $pair2 array( 'old' => 'old-key', 'new' => 'new-key' )
     * ...
     *
     */
    public function batchMove()
    {
        $keys = func_get_args();

        if( func_num_args() <= 0 ) {
            return array();
        }

        $entries = array();

        foreach( $keys as $i => $key ) {
            if( isset( $key['old'] ) AND !empty( $key['old'] ) AND isset( $key['new'] ) AND !empty( $key['new'] ) ) {
                $key['old'] = new \Qiniu_RS_EntryPath( $this->bucket, $key['old'] );
                $key['new'] = new \Qiniu_RS_EntryPath( $this->bucket, $key['new'] );
                $entries[$i] = new \Qiniu_RS_EntryPathPair( $key['old'], $key['new'] );
            } else {
                $entries[$i] = new \Qiniu_RS_EntryPathPair(
                    new \Qiniu_RS_EntryPath( $this->bucket, 'empty' ),
                    new \Qiniu_RS_EntryPath( $this->bucket, 'empty' )
                );
            }
        }

        list( $ret, $err ) = Qiniu_RS_BatchDelete($client, $entries);

        if( $err !== null ) {
            $ret['code'] = $err->Code;
            return $ret;
        } else {
            return $ret;
        }
    }

    /*
     * copy a resource
     */
    public function copy( $oldKey, $newKey, $newBucket = null )
    {
        if( $newBucket === null ) {
            $newBucket = $this->bucket;
        }

        $err = Qiniu_RS_Copy( $this->client, $this->bucket, $oldKey, $newBucket, $newKey );

        if( $err !== null ) {
            throw new QiniuCopyException( $err->Err, $err->Code, $key );
        } else {
            return true;
        }
    }

    /*
     * batch copy
     * param $pair array( 'from' => 'from-key', 'to' => 'to-key' )
     * param $pair2 array( 'from' => 'from-key', 'to' => 'to-key' )
     * ...
     */
    public function batchCopy( array $pairs )
    {
        $keys = func_get_args();

        if( func_num_args() <= 0 ) {
            return array();
        }

        $entries = array();

        foreach( $keys as $i => $key ) {
            if( isset( $key['from'] ) AND !empty( $key['from'] ) AND isset( $key['to'] ) AND !empty( $key['to'] ) ) {
                $key['from'] = new \Qiniu_RS_EntryPath( $this->bucket, $key['from'] );
                $key['to'] = new \Qiniu_RS_EntryPath( $this->bucket, $key['to'] );
                $entries[$i] = new \Qiniu_RS_EntryPathPair( $key['from'], $key['to'] );
            } else {
                $entries[$i] = new \Qiniu_RS_EntryPathPair(
                    new \Qiniu_RS_EntryPath( $this->bucket, 'empty' ),
                    new \Qiniu_RS_EntryPath( $this->bucket, 'empty' )
                );
            }
        }

        list( $ret, $err ) = Qiniu_RS_BatchCopy($client, $entries);

        if( $err !== null ) {
            $ret['code'] = $err->Code;
            return $ret;
        } else {
            return $ret;
        }
    }

    /*
     * get a resource attributes
     */
    public function stat( $key )
    {
        list( $ret, $err ) = Qiniu_RS_Stat( $this->client, $this->bucket, $key );

        if( $err !== null ) {
            throw new QiniuStatException( $err->Err, $err->Code, $key );
        } else {
            return $ret;
        }
    }

    /*
     * batch get resources attributes
     */
    public function batchStat()
    {
        $keys = func_get_args();

        if( func_num_args() <= 0 ) {
            return array();
        }

        foreach( $keys as $i => $key ) {
            $keys[$i] = new \Qiniu_RS_EntryPath( $this->bucket, $key );
        }

        list( $ret, $err ) = Qiniu_RS_BatchStat( $this->client, $keys );

        if( $err !== null ) {
            $ret['code'] = $err->Code;
            return $ret;
        } else {
            return $ret;
        }
    }

    /*
     * get image resource with custom size
     *
     * TODO this is a old qiniu api, don't use this
     *
     * read more: http://developer.qiniu.com/docs/v6/api/reference/obsolete/imageview.html
     */
    public function imageView( $width, $height, $key, $mode = 1 )
    {
        return true;
    }

    public function getKey($url)
    {
        return substr_replace($this->domain, '', $url);
    }

    public function getDomain()
    {
        return $this->domain;
    }

}
