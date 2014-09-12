<?php

/*
 * register qiniu php sdk as a service into symfony 2
 *
 * you can get it with $this->container->get("dwd_qiniu_sdk");
 *
 * see more qiniu php-sdk, read: https://github.com/qiniu/php-sdk/tree/develop/docs
 *
 */

namespace DWD\QiniuSdkBundle\Util;

use Symfony\Component\DependencyInjection\ContainerInterface;
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
   private $upToken = null;
   private $upTokenExpire = 0;
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
    */
   public function getUpToken()
   {
      if( $this->upToken === null OR time() > $this->upTokenExpire ) {
         $putPolicy = new \Qiniu_RS_PutPolicy( $this->bucket );
         $this->upToken = $putPolicy->Token(null);
         $this->upTokenExpire = time() + 3600; // qiniu sdk default uptoken expire time, in rs.php
      }

      return $this->upToken;
   }

   /*
    * upload a text to qiniu
    *
    * read: https://github.com/qiniu/php-sdk/tree/develop/docs#11%E4%B8%8A%E4%BC%A0%E6%B5%81%E7%A8%8B
    */
   public function put( $key, $content )
   {
      $upToken = $this->getUpToken();

      list($ret, $err) = Qiniu_Put( $upToken, $key, $content, null );

      if( $err !== null ) {
         throw new QiniuPutException( $err->Err, $err->Code, $key );
      } else {
         return $this->getBaseUrl( $ret['key'] );
      }
   }

   /*
    * upload a file to qiniu
    *
    * read: https://github.com/qiniu/php-sdk/tree/develop/docs#11%E4%B8%8A%E4%BC%A0%E6%B5%81%E7%A8%8B
    */
   public function putFile( $key, $filePath )
   {
      $upToken = $this->getUpToken();

      $putExtra = new \Qiniu_PutExtra();
      $putExtra->Crc32 = 1;

      list($ret, $err) = Qiniu_PutFile( $upToken, $key, $filePath, $putExtra );
      if( $err !== null ) {
         throw new QiniuPutException( $err->Err, $err->Code, $key );
      } else {
         return $this->getBaseUrl( $ret['key'] );
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
   public function batchDelete( array $pairs )
   {
       return true;
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
    */
   public function batchMove( array $pairs )
   {
       return true;
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
    */
   public function batchCopy( array $pairs )
   {
       return true;
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
}
