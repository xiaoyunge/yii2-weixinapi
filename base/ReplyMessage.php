<?php
namespace yangshihe\weixinapi\base;

use Yii;
use yii\helpers\Json;

/**
 *   回复被动的消息,返回
 *
 *@package common\helpers
 *@author yuzhiyang <yangshihe@qq.com>
 *@copyright yangshihe <yangshihe@qq.com/>
 *@version 1.0.0
 *@since 2016年8月11日
 *@todo
 */

class ReplyMessage
{

    public $ToUserName;

    public $FromUserName;

    public $mewsCount = 10;

    public function __construct($ToUserName, $FromUserName)
    {

        $this->ToUserName = $ToUserName;

        $this->FromUserName = $FromUserName;

        $this->init();


    }

    public function init()
    {

        if (empty($this->ToUserName) || empty($this->FromUserName)) {

            throw new  \yii\web\ConflictHttpException('ToUserName OR FromUserName MUST be set');
        }

    }

    /**
     * 创建微信格式的消息
     * @param mixed $item
     * @param string $type range in (text, image, voice, music, video, news)
     * @return mixed
     */

    public function message($item, $type = 'text')
    {
        if (in_array($type, ['text', 'image', 'voice', 'music', 'video', 'news'])) {

            return $this->$type($item);
        }

        return 'success';
    }

    private function wrapper($item)
    {
        $data = [
            'ToUserName' => $this->ToUserName,
            'FromUserName' =>$this->FromUserName,
            'CreateTime' => time(),

        ];

        if (!empty($item)) {

            return array_merge($data, $item);

            // Yii::$app->respone->format = 'xml';
            // Yii::$app->respone->content = $array;
            // // Yii::$app->send();
            // Yii::$app->end();

        }

        echo 'success';

    }


    /**
     * 创建文本消息// 可以使用这个返回 空消息
     *
     * @param string $content
     *
     * @return array
     */
    public function text($content)
    {

        $item = ['MsgType' => __FUNCTION__];

        $item['Content'] = $content;

       if($content) return $this->wrapper($item);

       return [];

    }

    /**
     * 创建图片消息//
     *
     * @param integer $MediaId
     *
     * @return array
     */
    public function image($MediaId)
    {

        $item = ['MsgType' => __FUNCTION__];

        $item['Image'] = [
            'MediaId' => $MediaId
        ];

        return $this->wrapper($item);


    }

    /**
     * 创建语音消息//
     *
     * @param integer $MediaId
     *
     * @return array
     */
    public function voice($MediaId)
    {

        $item = ['MsgType' => __FUNCTION__];

        $item['Voice'] = [
            'MediaId' => $MediaId
        ];

        return $this->wrapper($item);

    }


    /**
     * 创建视频消息//
     *
     * @param array $Voice item
     *
     * @return array
     */
    public function video($Voice)
    {

        $item = ['MsgType' => __FUNCTION__];

        $item['Voice'] = $Voice;

        // $Voice = [
        //     'MediaId' => $MediaId
        //     'Title' => $Title,
        //     'Description' => $Description
        // ];

        return $this->wrapper($item);

    }

    /**
     * 创建音乐消息//
     *
     * @param array $Music item
     *
     * @return array
     */
    public function music($Music)
    {

        $item = ['MsgType' => __FUNCTION__];

        $item['Music'] = $Music;

        // $Voice = [
        //     'Title' => $Title
        //     'Description' => $Description,
        //     'MusicURL' => $MusicURL,
        //     'HQMusicUrl' => $HQMusicUrl,
        //     'ThumbMediaId' => $ThumbMediaId
        // ];

        return $this->wrapper($item);


    }

    /**
     * 创建图文消息 简化了结构 只需传入数组包含 Title,Description,PicUrl,Url即可
     *
     * @param array $Articles item
     *
     * @return array
     */
    public function news($Articles)
    {

        $item = ['MsgType' => __FUNCTION__];

        /*
        $Articles = [[
            'Title' => '你好测试数据',
            'Description' => '你好测试数据',
            'PicUrl' => 'http://miniktv.noooya.com/upload/image/201608/201608061727447048.jpg',
            'Url' => 'http://miniktv.noooya.com/api/doc/guide-start.html'
        ],
        [
            'Title' => '你好测试数据2',
            'Description' => '你好测试数据2',
            'PicUrl' => 'http://miniktv.noooya.com/upload/image/201608/201608061727447048.jpg',
            'Url' => 'http://miniktv.noooya.com/api/doc/guide-start.html'
        ]];
        */

        $count = count($Articles);

        $arr = [];

        if ($count > $this->mewsCount) $count = $this->mewsCount;

        foreach ($Articles as $key => $value) {

            if ($key > $count) continue;

            $arr[] = $value;

        }

        $item['ArticleCount'] = $count;

        $item['Articles'] = $arr;

        return $this->wrapper($item);

    }




}
