<?php
    
    namespace App\Utils;
    
    /*
    edit by: @realfever
    date: 03/05/2021
    changed: 
    1. fixed can't run verify user's session
    2. added feature: auto-reply 
    3. deleted useless code, Telegram/Message.php (reason: not used) and merged it functions into this
    4. made menu easy to read
    */
    use App\Utils\Telegram;
    use App\Models\User;
    use App\Services\Config;
    use App\Controllers\LinkController;
    use TelegramBot\Api\Client;
    use TelegramBot\Api\Exception;
    use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
    use App\Services\MalioConfig;
    
    class TelegramProcess
    {
        private static $all_rss = [
            //请根据需求开启
            'clean_link' => '重置订阅',
            //'?sub=1' => 'SSR订阅',
            //'?sub=3' => 'V2ray订阅',
            '?sub=5' => 'Shadowrocket',
            '?sub=4' => 'Kitsunebi or V2rayNG or BifrostV',
            //'?surge=2' => 'Surge 2.x',
            //'?surge=3' => 'Surge 3.x',
            //'?ssd=1' => 'SSD',
            '?clash=1' => 'Clash',
            //'?surfboard=1' => 'Surfboard',
            //'?quantumult=3' => 'Quantumult(完整配置)'
        ];
    
        private static function callback_bind_method($bot, $callback)
        {
            $callback_data = $callback->getData();
            $message = $callback->getMessage();
            $reply_to = $message->getMessageId();
            $user = User::where('telegram_id', $callback->getFrom()->getId())->first();
            $reply_message = '？？？';
            if ($user != null) {
                switch (true) {
                    case $callback_data == '?quantumult=3':
                        $baseUrl = Config::get('subUrl');
                        $usertoken = LinkController::GenerateSSRSubCode($user->id, 0);
                        $subUrl = '?quantumult=3';
                        $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
                            [
                                [
                                    ['text' => '点击跳转', 'url' => $baseUrl . '/jump.html?url=quantumult://settings?configuration=clipboard']
                                ]
                            ]
                        );
                        $filepath = '/tmp/tg_' . $usertoken . '.txt';
                        $fh = fopen($filepath, 'w+');
                        $string = file_get_contents($baseUrl.$usertoken.$subUrl);
                        fwrite($fh, $string);
                        fclose($fh);
                        $reply_message = "感谢使用本机器人";
                        $bot->sendMessage($user->get_user_attributes('telegram_id'), "两种方法:\n 方法一:\n  1.点击打开以下配置文件\n  2. 选择分享->拷贝到\"Quantumult\"\n  3.选择更新配置\n 方法二:\n  1.长按配置文件\n  2. 选择更多->分享->拷贝\n  3.点击跳转APP,到Quan中保存", $parseMode = null, $disablePreview = false, $replyToMessageId = null, $replyMarkup = $keyboard);
                        $bot->sendDocument($user->get_user_attributes('telegram_id'), new \CURLFile($filepath, '', 'quantumult_' . $usertoken . '.conf'));
                        unlink($filepath);
                        break;
                    case (strpos($callback_data, 'sub') or strpos($callback_data, 'surge') or strpos($callback_data, 'clash') or strpos($callback_data, 'surfboard')):
                        $ssr_sub_token = LinkController::GenerateSSRSubCode($user->id, 0);
                        $subUrl = Config::get('subUrl');
                        $reply_message = self::$all_rss[$callback_data] . ': ' . $subUrl . $ssr_sub_token . $callback_data . PHP_EOL;
                        break;
                    case ($callback_data == 'clean_link'):
                        $user->clean_link();
                        $reply_message = '链接重置成功';
                        break;
                }
                $bot->sendMessage($message->getChat()->getId(), $reply_message, $parseMode = null, $disablePreview = false, $replyToMessageId = $reply_to);
            }
        }
        
        private static function needbind_method($bot, $message, $command, $user, $reply_to = null)
        {
            $reply = [
                'message' => '？？？',
                'markup' => null,
            ];
            if ($user != null) {
                switch ($command) {
                    case 'traffic':
                        $reply['message'] = '您当前的流量状况：
    今日已使用 ' . $user->TodayusedTraffic() . ' ' . number_format(($user->u + $user->d - $user->last_day_t) / $user->transfer_enable * 100, 2) . '%
    今日之前已使用 ' . $user->LastusedTraffic() . ' ' . number_format($user->last_day_t / $user->transfer_enable * 100, 2) . '%
    未使用 ' . $user->unusedTraffic() . ' ' . number_format(($user->transfer_enable - ($user->u + $user->d)) / $user->transfer_enable * 100, 2) . '%';
                        break;
                    case 'checkin':
                        if (!$user->isAbleToCheckin()) {
                            $reply['message'] = '大老爷，这种事一天只可以做一次的啊';
                            break;
                        }
                        $traffic = random_int(Config::get('checkinMin'), Config::get('checkinMax'));
                        $user->transfer_enable += Tools::toMB($traffic);
                        $user->last_check_in_time = time();
                        $user->save();
                        $reply['message'] = '这么快就完事了？获得了 ' . $traffic . ' MB 奇怪液体！';
                        break;
                    case 'prpr':
                        $prpr = array('⁄(⁄ ⁄•⁄ω⁄•⁄ ⁄)⁄', '(≧ ﹏ ≦)', '(*/ω＼*)', 'ヽ(*。>Д<)o゜', '(つ ﹏ ⊂)', '( >  < )');
                        $reply['message'] = $prpr[random_int(0, 5)];
                        break;
                    case 'rss':
                        $reply['message'] = '点击以下按钮获取对应订阅: ';
                        $keys = [];
                        foreach (self::$all_rss as $key => $value) {
                            $keys[] = [['text' => $value, 'callback_data' => $key]];
                        }
                        $reply['markup'] = new InlineKeyboardMarkup(
                            $keys
                        );
                        break;
                }
            } else {
                $reply['message'] = '您未绑定本站账号，您可以进入网站的“资料编辑”，在右下方绑定您的账号';
            }
    
            return $reply;
        }
    
    
        public static function telegram_process($bot, $message, $command)
        {
            $user = User::where('telegram_id', $message->getFrom()->getId())->first();
            $reply_to = $message->getMessageId();
            $reply = [
                'message' => '不够涩= =',
                'markup' => null,
            ];
            if ($message->getChat()->getId()) {
                //消息回复，默认/rss功能不在群组内启用
                $bot->sendChatAction($message->getChat()->getId(), 'typing');
    
                switch ($command) {
                    case 'ping':
                        $MessageData = $message->getText();
                        $f = fopen("/www/wwwroot/trojan_ssp/tg_log/log.txt","w");
                        fwrite($f,$MessageData);
                        fclose($f);
                        $reply['message'] = 'Pong!您的 ID 是 ' . $message->getChat()->getId() . '!';
                        if($message->getChat()->getId() == Config::get('telegram_chatid')){
                                //$reply['message'] = 'pong';
                                //如果不想显示群组ID请取消上面的注释
                            }
                        break;
                    case 'traffic':
                        $reply = self::needbind_method($bot, $message, $command, $user, $reply_to);
                        
                        break;
                    case 'checkin':
                        $reply = self::needbind_method($bot, $message, $command, $user, $reply_to);
                        
                        break;
                    case 'prpr':
                        $reply = self::needbind_method($bot, $message, $command, $user, $reply_to);
                        
                        break;
                    case 'rss':
                        if($message->getChat()->getId() == Config::get('telegram_chatid')){
                            $reply['message'] = '住手，这里不可以干那种事啊~';
                            break;
                        }
                        $reply = self::needbind_method($bot, $message, $command, $user, $reply_to);
                        
                        break;
                    case 'help':
                        $reply['message'] = 
                        "指令列表：\n/prpr 舔\n/ping  获取群组ID\n/traffic 查询流量\n/checkin 签到\n/help 获取帮助信息\n/rss 获取节点订阅\n\n自动回复（无需加/）：\n官网 官网地址\nApple ID 小火箭免费下载账号";
                        if ($user == null) {
                            $reply['message'] .= PHP_EOL . '您未绑定本站账号，您可以进入网站的“资料编辑”，在右下方绑定您的账号';
                        }
                        break;
                    default:
                        if ($message->getPhoto() == null) {
                            $MessageData = $message->getText();
                            if(strstr($MessageData,"apple") && strstr($MessageData,"id")){
                                $reply['message'] = "免费下载Shadowrocket！\n\nApple ID\n账号：xxx\n密码：xxx\n\n不要在iCloud登陆！！！！\n不要在iCloud登陆！！！！\n不要在iCloud登陆！！！！\n\nIOS 14的用户 在安全项选择不升级";
                                break;
                            }
                            if(strstr($MessageData,"Apple") && strstr($MessageData,"ID")){
                                $reply['message'] = "免费下载Shadowrocket！\n\nApple ID\n账号：xxx\n密码：xxx\n\n不要在iCloud登陆！！！！\n不要在iCloud登陆！！！！\n不要在iCloud登陆！！！！\n\nIOS 14的用户 在安全项选择不升级";
                                break;
                            }
                            if(strstr($MessageData,"官网")){
                                $reply['message'] = "回家地址".Config::get('baseUrl')."\n";
                                break;
                            }
                            if(strstr($MessageData,"跑路")){
                                $reply['message'] = "跑路？再说本智障姬要生气了！";
                                break;
                            }
                            if(strstr($MessageData,"套餐") && strstr($MessageData,"叠加")){
                                $reply['message'] = "温馨提示，所有套餐均不可叠加";
                                break;
                            }
                            if(strstr($MessageData,"表白智障姬")){
                                $reply['message'] = "mua~智障姬也爱你！";
                                break;
                            }
                            if(strstr($MessageData,"快吗") || strstr($MessageData,"速度")){
                                $reply['message'] = "不快本智障卖你干啥";
                                break;
                            }
                            if(strlen($message->getText()) != 16){
                                if ((!(is_numeric($message->getText()) && strlen($message->getText()) == 6 ))) {
                                    $reply['message'] = Tuling::chat($message->getFrom()->getId(), $message->getText());
                                    break;
                                }
                            }
                            
    
                            if ($user != null) {
                                
                                $uid = TelegramSessionManager::verify_login_number($message->getText(), $user->id);
                                if ($uid != 0) {
                                    $reply['message'] = '登录验证成功，邮箱：' . $user->email;
                                } else {
                                    $reply['message'] = '登录验证失败，数字无效';
                                }
                                break;
                            }
                            if(strlen($message->getText()) == 16){
                                    $MessageData = $message->getText();
                                    $Uid = TelegramSessionManager::verify_bind_session($MessageData);
                                    $reply['message'] = "绑定失败，请刷新绑定码！";
                                    if($Uid != 0){
                                        $elink  = User::where('id',$Uid)->first();
                                        $elink->telegram_id = $message->getFrom()->getId();
                                        $elink->save();
                                        $reply['message'] = "绑定成功";
                                    }
                            }
                            else{
                                $reply['message'] = '登录验证失败，您未绑定本站账号';
                            }
                            //$reply['message'] = strlen($message->getText());
                            
                            break;
                        }
                        $photos = $message->getPhoto();
    
                        $photo_size_array = array();
                        $photo_id_array = array();
                        $photo_id_list_array = array();
    
                        foreach ($photos as $photo) {
                            $file = $bot->getFile($photo->getFileId());
                            $real_id = substr($file->getFileId(), 0, 36);
                            if (!isset($photo_size_array[$real_id])) {
                                $photo_size_array[$real_id] = 0;
                            }
    
                            if ($photo_size_array[$real_id] >= $file->getFileSize()) {
                                continue;
                            }
    
                            $photo_size_array[$real_id] = $file->getFileSize();
                            $photo_id_array[$real_id] = $file->getFileId();
                            if (!isset($photo_id_list_array[$real_id])) {
                                $photo_id_list_array[$real_id] = array();
                            }
    
                            $photo_id_list_array[$real_id][] = $file->getFileId();
                        }
    
                        foreach ($photo_id_array as $key => $value) {
                            $file = $bot->getFile($value);
                            $qrcode_text = QRcode::decode('https://api.telegram.org/file/bot' . Config::get('telegram_token') . '/' . $file->getFilePath());
    
                            if ($qrcode_text == null) {
                                foreach ($photo_id_list_array[$key] as $fail_key => $fail_value) {
                                    $fail_file = $bot->getFile($fail_value);
                                    $qrcode_text = QRcode::decode('https://api.telegram.org/file/bot' . Config::get('telegram_token') . '/' . $fail_file->getFilePath());
                                    if ($qrcode_text != null) {
                                        break;
                                    }
                                }
                            }
    
                            if (strpos($qrcode_text, 'mod://bind/') === 0 && strlen($qrcode_text) == 27) {
                                $uid = TelegramSessionManager::verify_bind_session(substr($qrcode_text, 11));
                                if ($uid == 0) {
                                    $reply['message'] = '绑定失败，二维码无效：' . substr($qrcode_text, 11) . '二维码的有效期为10分钟，请尝试刷新网站的“资料编辑”页面以更新二维码';
                                    continue;
                                }
                                $user = User::where('id', $uid)->first();
                                $user->telegram_id = $message->getFrom()->getId();
                                $user->im_type = 4;
                                $user->im_value = $message->getFrom()->getUsername();
                                $user->save();
                                $reply['message'] = '绑定成功，邮箱：' . $user->email;
                                break;
                            }
    
                            if (strpos($qrcode_text, 'mod://login/') === 0 && strlen($qrcode_text) == 28) {
                                if ($user == null) {
                                    $reply['message'] = '登录验证失败，您未绑定本站账号' . substr($qrcode_text, 12);
                                    break;
                                }
    
                                $uid = TelegramSessionManager::verify_login_session(substr($qrcode_text, 12), $user->id);
                                if ($uid != 0) {
                                    $reply['message'] = '登录验证成功，邮箱：' . $user->email;
                                } else {
                                    $reply['message'] = '登录验证失败，二维码无效' . substr($qrcode_text, 12);
                                }
                                break;
                            }
    
                            break;
                        }
                }
            } else {
                //不需要群组分支，如果不在群组里启用消息，在上面将条件改为和/rss命令一样就行
            }
            
            $bot->sendMessage($message->getChat()->getId(), $reply['message'], $parseMode = null, $disablePreview = false, $replyToMessageId = $reply_to, $replyMarkup = $reply['markup']);
            $bot->sendChatAction($message->getChat()->getId(), '');
        }
    
        public static function process()
        {
            try {
                $bot = new Client(Config::get('telegram_token'));
                // or initialize with botan.io tracker api key
                // $bot = new \TelegramBot\Api\Client('YOUR_BOT_API_TOKEN', 'YOUR_BOTAN_TRACKER_API_KEY');
    
                $command_list = array('ping', 'traffic', 'help', 'prpr', 'checkin', 'rss');
                foreach ($command_list as $command) {
                    $bot->command($command, static function ($message) use ($bot, $command) {
                        TelegramProcess::telegram_process($bot, $message, $command);
                    });
                }
    
                $bot->on($bot->getEvent(static function ($message) use ($bot) {
                    TelegramProcess::telegram_process($bot, $message, '');
                }), static function () {
                    return true;
                });
    
                $bot->on($bot->getCallbackQueryEvent(function ($callback) use ($bot) {
                    TelegramProcess::callback_bind_method($bot, $callback);
                }), function () {
                    return true;
                });
    
                $bot->run();
            } catch (Exception $e) {
                $e->getMessage();
            }
        }
    }