<?php

namespace DWD\QiniuSdkBundle\Util;

use Symfony\Component\DependencyInjection\ContainerInterface;
use DWD\QiniuSdkBundle\Exception\QiniuPutException;

class Qiniu
{
   private $accessKey;
   private $secretKey;
   private $bucket;
   private $domain;
   private $upToken = null;
   private $upTokenExpire = 0;

   public function __construct( ContainerInterface $container, $accessKey, $secretKey, $bucket, $domain )
   {
      $this->container = $container;
      $this->accessKey = $accessKey;
      $this->secretKey = $secretKey;
      $this->bucket = $bucket;
      $this->domain = $domain;
      $this->upTokenExpire = time();

      Qiniu_SetKeys( $this->accessKey, $this->secretKey );
   }

   public function getBaseUrl( $key )
   {
      return Qiniu_RS_MakeBaseUrl( $this->domain, $key );
   }

   public function getUpToken()
   {
      if( $this->upToken === null OR time() > $this->upTokenExpire ) {
         $putPolicy = new \Qiniu_RS_PutPolicy( $this->bucket );
         $this->upToken = $putPolicy->Token(null);
      }

      return $this->upToken;
   }

   public function put( $key, $content )
   {
      $upToken = $this->getUpToken();

      list($ret, $err) = Qiniu_Put( $upToken, $key, $content, null );

      if( $err !== null ) {
         throw new QiniuPutException( "Code: {$err->Code}, Key: {$key}, {$err->Err}" );
      } else {
         return $this->getBaseUrl( $ret['key'] );
      }
   }

   public function putFile( $key, $filePath )
   {
      $upToken = $this->getUpToken();

      $putExtra = new Qiniu_PutExtra();
      $putExtra->Crc32 = 1;

      list($ret, $err) = Qiniu_PutFile( $upToken, $key, $filePath, $putExtra );
      if( $err !== null ) {
         throw new QiniuPutException( "Code: {$err->Code}, Key: {$key}, {$err->Err}" );
      } else {
         return $this->getBaseUrl( $ret['key'] );
      }
   }
}
