<?php

use think\facade\Route;

Route::rule('/$', function () {
    return 'hello,ThinkPHP6! home';
});

// 存活检测
Route::rule('check/check$', 'home/api/check'); // 选择支付方式

// 关于用户
Route::rule('user/user/index$', 'home/user.user/index');//用户个人信息
Route::rule('login/login/index$', '/home/login.login/index');//登录
Route::rule('login/login/register$', '/home/login.login/register');//自动注册
Route::rule('login/login/user_register$', '/home/login.login/user_register');//手动注册
Route::rule('login/login/change_pwd$', '/home/login.UpdatePwd/change_pwd');//修改密码
Route::rule('login/login/forget$', '/home/login.login/forget');//忘记密码
Route::rule('user/user/sign_out$', '/home/user.user/sign_out');//退出登录
Route::rule('qrcode/code/qrcode$', '/home/qrcode.code/qrcode');//生成邀请二维码
Route::rule('user/synchronous/orders$', '/home/user.user/syn_order');//临时用户数据转移

//商品 
Route::rule('goods/goods/level$', '/home/goods.goods/level');//获取购买套餐
Route::rule('goods/goods/purchase$', '/home/goods.goods/purchase');//购买套餐
Route::rule('goods/goods/popular_movies$', '/home/common.common/popular_movies');//首页分类视频列表
Route::rule('goods/goods/goods_auth_list$', '/home/goods.goods/goods_auth_list');//视频列表
Route::rule('goods/video/free_time$', '/home/goods.goods/free_time');//免费时长
Route::rule('goods/goods/goods_list$', '/home/goods.goods/goods_list');//购买已购买视频列表
Route::rule('goods/goods/alone_list$', '/home/goods.goods/alone_list');//获取已经购买的单片视频列表
Route::rule('goods/goods/video_type_list$', '/home/common.common/video_type_list');//获取视频分类
Route::rule('goods/goods/video_tag_list$', '/home/common.common/video_tag_list');//获取视频分类
Route::rule('goods/goods/alone_video_purchase$', '/home/goods.goods/alone_video_purchase');//视频单独购买
Route::rule('goods/goods/recharge$', '/home/goods.goods/recharge');//充值
Route::rule('goods/goods/video$', '/home/goods.goods/video');//获取当前视频
Route::rule('goods/goods/reckon$', '/home/goods.goods/reckon');//统计当前播放量
Route::rule('goods/goods/continuepay$', '/home/goods.goods/continuepay');//查看支付结果
Route::rule('goods/goods/already_purchased$', '/home/user.user/already_purchased');//是否购买过视频或套餐

//订单
Route::rule('order/order/level$', '/home/order.order/level');//获取套餐
Route::rule('order/order/video$', '/home/order.order/video');//获取购买套餐
Route::rule('order/order/list$', '/home/order.order/list');//订单列表

// 轮播
Route::rule('index/carouse$', 'home/index/carouse');//轮播
Route::rule('index/getskin$', 'home/api/getskin');//皮肤
Route::rule('index/accessrecord$', 'home/index/accessRecord');//访问统计
Route::rule('index/getffurl$', 'home/api/getFangFengUrl');//获取短域名 推广链接
Route::rule('index/getvideo$', 'home/index/getvideo');//获取弹窗视频
Route::rule('index/getvideoinfo$', 'home/index/getvideoinfo');//获取弹窗视频
Route::rule('index/getsystitle$', 'home/common.common/getsystitle');//获取系统名称
Route::rule('index/get_conf_value$', 'home/common.common/getConfValue');//获取系统名称

// 支付
Route::rule('pay/choice$', 'home/pay.index/choice'); // 选择支付方式

// 所有回调的 通道
Route::rule('pay/asyncbacktest$', 'home/pay.back/async_back_test'); // 支付通道2 小语
Route::rule('pay/asyncback2$', 'home/pay.back/async_passage2'); // 支付通道2 小语
Route::rule('pay/asyncback3$', 'home/pay.back/async_passage3'); // 支付通道3 巅峰支付
Route::rule('pay/asyncback4$', 'home/pay.back/async_passage4'); // 支付通道4 苍龙支付
Route::rule('pay/asyncback5$', 'home/pay.back/async_passage5'); // 支付通道5 山河支付
Route::rule('pay/asyncback8$', 'home/pay.back/async_passage8'); // 支付通道8 金子支付
Route::rule('pay/asyncbackyian$', 'home/pay.back/asyncbackyian'); // 支付通道 易安支付
Route::rule('pay/asyncbackjiguang$', 'home/pay.back/asyncbackjiguang'); // 支付通道 极光支付




















