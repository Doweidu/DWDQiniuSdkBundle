# 七牛PHP-SDK Bundle #
在symfony2中使用七牛的SDK

## 安装 ##
编辑```composer.json```，添加bundle的地址，并添加依赖：

```
...
"repositories": [
...
    {
        "type": "vcs",
        "url": "git@bitbucket.org:duoweidu/dwdqiniubundle.git"
    }
],
...
"require": {
...
    "dwd/qiniu-sdk-bundle": "dev-master",
...
}
...
```

修改配置文件 ```app/config/parameters.yml.dist```添加以下几项配置项：
```
parameters:
...
    qiniu_sdk_accessKey: ~
    qiniu_sdk_secretKey: ~
    qiniu_sdk_bucket: ~
    qiniu_sdk_domain: ~
```

更新依赖：
```
$ composer update dwd/qiniu-sdk-bundle
```

修改```app/AppKernel.php```，加载```DWDQiniuSdkBundle```：
```
$bundles = array(
...
    new DWD\QiniuSdkBundle\DWDQiniuSdkBundle(),
);
```

清除缓存：
```
app/console cache:clear
```

## 使用 ##
PHP中：
```
$qiniu = $this->container->get('dwd_qiniu_sdk');
$rs = $qiniu->put('<key-name>', '<Content>'); //上传文本内容，可以直接把内容上传，使用key可以取到一个文本资源
$rs = $qiniu->putFile('<key-name>', '<file-path>'); //上传本地文件，指定文件路径即可
$rs = $qiniu->delete('<key-name>'); //从七牛删除一个资源
$rs = $qiniu->batchDelete('<key1-name>'[, '<key2-name>', ...]); //从七牛删除同一个bucket的多个资源
$rs = $qiniu->mv('<old-key-name>', 'new-key-name', '<new-bucket-name>'); //移动一个资源，第三个参数可选，默认是同一个bucket内移动
$rs = $qiniu->batchMv(array('old'=>'<old-key1-name>', 'new'=>'new-key1-name')[, array('old'=>'<old-key2-name>', 'new'=>'new-key2-name'), ...]); //移动同一个bucket的多个资源
$rs = $qiniu->copy('<from-key-name>', 'to-key-name', '<new-bucket-name>'); //复制一个资源，第三个参数可选，默认是同一个bucket内复制
$rs = $qiniu->batchCopy(array('from'=>'<from-key1-name>', 'to'=>'to-key1-name')[, array('from'=>'<from-key2-name>', 'to'=>'to-key2-name'), ...]); //复制同一个bucket的多个资源
$rs = $qiniu->stat('<key-name>'); //获取资源信息，包括文件大小(fsize)，哈希值(hash)，类型(mimeType)，上传时间(putTime)
$rs = $qiniu->batchStat('<key1-name>'[, '<key2-name>', ...]); //批量获取文件信息，返回一个数组，code是指资源状态，正常资源是200，data中是和stat相同字段的列表数组
```

