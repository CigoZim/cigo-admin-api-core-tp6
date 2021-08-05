<?php

namespace cigoadmin\controller;

use cigoadmin\library\ErrorCode;
use cigoadmin\library\HttpReponseCode;
use cigoadmin\library\traites\ApiCommon;
use cigoadmin\library\uploader\tencent\Sts;
use cigoadmin\library\uploader\UploadMg;
use cigoadmin\model\Files;
use Qiniu\Auth;
use Qcloud\Cos\Client;
use think\facade\Config;
use think\facade\Request;
use think\Model;

/**
 * Trait FileUpload
 * @package cigoadmin\controller
 */
trait FileUpload
{
    use ApiCommon;


    private function makeToken()
    {
        $res = false;

        switch (env('cigo-admin.file-save-type')) {
            case 'cloudQiniu':
                $res = $this->makeCloudQiniuToken();
                break;
            case 'cloudAliyun':
                $res = $this->makeCloudAliyunToken();
                break;
            case 'cloudTencent':
                $res = $this->makeCloudTencentToken();
                break;
            default:
                $res = $this->makeApiReturn("系统云存储配置错误", [], ErrorCode::ServerError_OTHER_ERROR, HttpReponseCode::ServerError_InternalServer_Error);
                break;
        }
        return $res;
    }

    /******************************= 七牛云：开始 =**********************************/

    /**
     * 文件上传
     */
    private function localUpload()
    {
        //1. 实例化上传类，并创建文件上传实例
        $upMg = new UploadMg();
        $upMg->init()->makeFileUploader();

        //2. 执行上传操作
        $upMg->doUpload();
    }

    /******************************= 七牛云：开始 =**********************************/
    /**
     * 创建七牛云上传凭证
     */
    private function makeCloudQiniuToken()
    {
        //检查参数
        if (!isset($this->args['bucketType']) ||  !in_array($this->args['bucketType'], ['img', 'video', 'open'])) {
            return $this->makeApiReturn('存储空间不存在', [], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }
        $qiniuConfig = Config::get('cigoadmin.qiniu_cloud');
        $bucket = $qiniuConfig['bucketList'][$this->args['bucketType']];

        // -------------------
        $auth = new Auth($qiniuConfig['AccessKey'], $qiniuConfig['SecretKey']);
        $policy = $qiniuConfig['enableCallbackServer']
            ? [
                'callbackUrl' => $qiniuConfig['callbackUrl'],
                'callbackBodyType' => $qiniuConfig['callbackBodyType'],
                'callbackBody' => $qiniuConfig['callbackBody'],
            ]
            : [
                'returnBody' => $qiniuConfig['returnBody']
            ];

        $uploadToken = $auth->uploadToken(
            $bucket,
            null,
            $qiniuConfig['tokenDuration'],
            $policy,
            true
        );

        return $this->makeApiReturn('获取成功', [
            'token' => $uploadToken,
            'platform' => env('cigo-admin.file-save-type', 'cloudQiniu'),
            'upload_host' => $qiniuConfig['host']
        ]);
    }

    /**
     * 七牛云文件上传通知
     */
    private function cloudQiniuNotify()
    {
        //开始对七牛回调进行鉴权
        $qiniuConfig = Config::get('cigoadmin.qiniu_cloud');
        $auth = new Auth($qiniuConfig['AccessKey'], $qiniuConfig['SecretKey']);
        $authorization = $_SERVER['HTTP_AUTHORIZATION'];
        $callbackBody = file_get_contents('php://input'); //获取回调的body信息
        $isQiniuCallback = $auth->verifyCallback($qiniuConfig['callbackBodyType'], $authorization, $qiniuConfig['callbackUrl'], $callbackBody);
        if (!$isQiniuCallback) {
            $this->args['isQiniuCallback'] = $isQiniuCallback;
            $this->args['authorization'] = $authorization;
            $this->args['callbackBody'] = $callbackBody;

            return $this->makeApiReturn('七牛回调鉴权失败', $this->args);
        }
        try {
            //保存文件信息到数据库
            $file = Files::where([
                ['platform', '=', 'qiniu'],
                ['platform_bucket', '=', $this->args['bucket']],
                ['platform_key', '=', $this->args['key']],
                ['name', '=', $this->args['fname']],
                ['hash', '=', $this->args['hash']]
            ])->findOrEmpty();
            if ($file->isEmpty()) {
                $fprefix = pathinfo($this->args['fname'], PATHINFO_FILENAME);
                $ext = pathinfo($this->args['fname'], PATHINFO_EXTENSION);
                $type = in_array($ext, ['png', 'jpg', 'jpeg', 'bmp', 'gif'])
                    ? 'img'
                    : (in_array($ext, ['mp4', 'rmvb', 'mov'])
                        ? 'video'
                        : 'file');
                $file = Files::create([
                    'platform' => 'qiniu',
                    'platform_bucket' => $this->args['bucket'],
                    'platform_key' => $this->args['key'],
                    'type' => $type,
                    'name' => $this->args['fname'],
                    'prefix' => $fprefix,
                    'ext' => $ext,
                    'name_saved' => $this->args['key'],
                    'mime' => $this->args['mimeType'],
                    'hash' => $this->args['hash'],
                    'size' => intval($this->args['fsize']),
                    'create_time' => time(),
                ]);
            }


            $fileInfo = [
                'id' => $file->id,
                'platform' => $file->platform,
                'platform_bucket' => $file->platform_bucket,
                'platform_key' => $file->platform_key,
                'name' => $file->name,
                'prefix' => $file->prefix,
                'ext' => $file->ext,
                'mime' => $file->mime,
                'hash' => $file->hash,
                'size' => $file->size,
                'create_time' => $file->create_time,
                'callbackBody' => $callbackBody
            ];
            // 补充文件信息：生成访问防盗链链接
            $this->appendFileInfoCloudQiniu($fileInfo);
            return $this->makeApiReturn('上传成功', $fileInfo);
        } catch (\Exception $exception) {
            return $this->makeApiReturn($exception->getMessage(), json_encode($exception), JSON_UNESCAPED_UNICODE);
        }
    }

    private function appendFileInfoCloudQiniu(&$info = [])
    {
        // 生成访问防盗链链接
        $qiniuConfig = Config::get('cigoadmin.qiniu_cloud');
        $auth = new Auth($qiniuConfig['AccessKey'], $qiniuConfig['SecretKey']);
        $bucketDomain = array_search($info['platform_bucket'], $qiniuConfig['domainLinkBucket']);
        $signedUrl = Request::scheme() . '://' . $qiniuConfig['domainList'][$bucketDomain] . '/' . $info['platform_key'];
        if (stripos($info['platform_bucket'], '_open') == false) {
            // 私有空间中的防盗链外链
            $signedUrl = $auth->privateDownloadUrl($signedUrl, $qiniuConfig['linkTimeout']);
        }
        $info['signed_url'] = $signedUrl;
    }
    /******************************= 七牛云：结束 =*********************************/

    /******************************= 腾讯云：开始 =*********************************/

    /**
     * 创建腾讯云上传凭证
     */
    private function makeCloudTencentToken()
    {
        //检查参数
        if (!isset($this->args['bucketType']) ||  !in_array($this->args['bucketType'], ['img', 'video', 'open'])) {
            return $this->makeApiReturn('存储空间不存在', [], ErrorCode::ClientError_ArgsWrong, HttpReponseCode::ClientError_BadRequest);
        }
        $tencentConfig = Config::get('cigoadmin.tencent_cloud');
        $bucket = $tencentConfig['bucketList'][$this->args['bucketType']];

        // -------------------
        $sts = new Sts();
        $config = array(
            'url' => 'https://sts.tencentcloudapi.com/',
            'domain' => 'sts.tencentcloudapi.com',
            'proxy' => '',
            'secretId' => $tencentConfig['SecretId'], // 固定密钥
            'secretKey' => $tencentConfig['SecretKey'], // 固定密钥
            'bucket' => $bucket,
            'region' => $tencentConfig['region'],
            'durationSeconds' => $tencentConfig['tokenDuration'],
            'allowPrefix' => $tencentConfig['prefix'] . "*",
            'allowActions' => ['name/cos:PutObject', 'name/cos:PostObject']
        );

        // 获取临时密钥，计算签名
        $credentialObj = $sts->getTempKeys($config);
        //追加字段
        $credentialObj['platform'] = env('cigo-admin.file-save-type', 'cloudTencent');
        $credentialObj['bucket'] = $bucket;
        $credentialObj['region'] = $tencentConfig['region'];
        $credentialObj['prefix'] = $tencentConfig['prefix'];
        $credentialObj['callback-url'] = $tencentConfig['callbackUrl'];

        return $this->makeApiReturn('获取成功', $credentialObj);
    }

    /**
     * 腾讯云文件上传通知
     */
    private function cloudTencentNotify()
    {
        try {
            //保存文件信息到数据库
            $file = Files::where([
                ['platform', '=', 'tencent'],
                ['platform_bucket', '=', $this->args['bucket']],
                ['platform_key', '=', $this->args['key']],
                ['hash', '=', $this->args['hash']],
                ['name', '=', $this->args['fname']],
            ])->findOrEmpty();
            if ($file->isEmpty()) {
                $fprefix = pathinfo($this->args['fname'], PATHINFO_FILENAME);
                $ext = pathinfo($this->args['fname'], PATHINFO_EXTENSION);
                $type = in_array($ext, ['png', 'jpg', 'jpeg', 'bmp', 'gif'])
                    ? 'img'
                    : (in_array($ext, ['mp4', 'rmvb', 'mov'])
                        ? 'video'
                        : 'file');
                $file = Files::create([
                    'platform' => 'tencent',
                    'platform_bucket' => $this->args['bucket'],
                    'platform_key' => $this->args['key'],
                    'type' => $type,
                    'name' => $this->args['fname'],
                    'prefix' => $fprefix,
                    'ext' => $ext,
                    'name_saved' => $this->args['key'],
                    'mime' => $this->args['mimeType'],
                    'hash' => $this->args['hash'],
                    'size' => intval($this->args['fsize']),
                    'create_time' => time(),
                ]);
            }

            $fileInfo = [
                'id' => $file->id,
                'platform' => $file->platform,
                'platform_bucket' => $file->platform_bucket,
                'platform_key' => $file->platform_key,
                'name' => $file->name,
                'prefix' => $file->prefix,
                'ext' => $file->ext,
                'mime' => $file->mime,
                'hash' => $file->hash,
                'size' => $file->size,
                'create_time' => $file->create_time
            ];

            // 补充文件信息：生成访问防盗链链接
            $this->appendFileInfoCloudTencent($fileInfo);

            return $this->makeApiReturn('上传成功', $fileInfo);
        } catch (\Exception $exception) {
            return $this->makeApiReturn($exception->getMessage(), json_encode($exception), JSON_UNESCAPED_UNICODE);
        }
    }

    private function appendFileInfoCloudTencent(&$info = [])
    {
        // 生成访问防盗链链接
        $tencentConfig = Config::get('cigoadmin.tencent_cloud');
        $config = [
            'region' => $tencentConfig['region'],
            'schema' => 'https', //协议头部，默认为 http
            'credentials' => array(
                'secretId'  => $tencentConfig['SecretId'],
                'secretKey' => $tencentConfig['SecretKey']
            )
        ];
        $cosClient = new Client($config);
        try {
            $signedUrl = $cosClient->getObjectUrl($info['platform_bucket'], $info['platform_key'], $tencentConfig['linkTimeout']);
        } catch (\Exception $e) {
            $info['signed_url'] = "加签失败，请检查";
            return;
        }
        $bucketDomain = array_search($info['platform_bucket'], $tencentConfig['domainLinkBucket']);
        $info['signed_url'] = $tencentConfig['cdnScheme'] . '://' . $tencentConfig['domainList'][$bucketDomain] . '/' . substr($signedUrl, strripos($signedUrl, '.com/') + 5);
    }

    /******************************= 腾讯云：结束 =*********************************/

    /******************************= 阿里云：开始 =*********************************/

    /**
     * 创建腾讯云上传凭证
     */
    private function makeCloudAliyunToken()
    {
        return $this->makeApiReturn('测试腾讯云存储', [
            'token' => "tencent-token",
            'upload_host' => "tencent-host"
        ]);
    }

    /**
     * 腾讯云文件上传通知
     */
    private function cloudAliyunNotify()
    {
    }
    /******************************= 阿里云：结束 =*********************************/

    /**
     * 获取文件信息
     * @param int $fileId
     * @return array|Model|null
     */
    private function getFileInfo($fileId = 0)
    {
        if (empty($fileId)) {
            return null;
        }
        $info = Files::where('id', $fileId)->findOrEmpty();
        if ($info->isEmpty()) {
            return null;
        }
        switch ($info['platform']) {
            case 'qiniu': //七牛云
                $this->appendFileInfoCloudQiniu($info);
                break;
            case 'tencent': //腾讯云
                $this->appendFileInfoCloudTencent($info);
                break;
            case 'aliyun': //阿里云
            case 'local': //本地服务器
            default:
                break;
        }

        return $info;
    }
}
