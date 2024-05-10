/*
 Navicat Premium Data Transfer

 Source Server         : localhost_3306
 Source Server Type    : MySQL
 Source Server Version : 80012 (8.0.12)
 Source Host           : localhost:3306
 Source Schema         : yum

 Target Server Type    : MySQL
 Target Server Version : 80012 (8.0.12)
 File Encoding         : 65001

 Date: 10/05/2024 12:59:51
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for auth
-- ----------------------------
DROP TABLE IF EXISTS `auth`;
CREATE TABLE `auth`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
  `auth` int(11) NOT NULL COMMENT '权限组',
  `name` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '权限名',
  PRIMARY KEY (`id`, `auth`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 4 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of auth
-- ----------------------------
INSERT INTO `auth` VALUES (1, 999, 'admin');
INSERT INTO `auth` VALUES (2, 666, 'vip');
INSERT INTO `auth` VALUES (3, 0, 'user');

-- ----------------------------
-- Table structure for captcha
-- ----------------------------
DROP TABLE IF EXISTS `captcha`;
CREATE TABLE `captcha`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
  `email` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '邮箱',
  `captcha` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '验证码',
  `out_time` datetime NOT NULL COMMENT '超时时间',
  `is_use` int(11) NOT NULL DEFAULT 0 COMMENT '是否被使用',
  PRIMARY KEY (`id`, `email`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of captcha
-- ----------------------------

-- ----------------------------
-- Table structure for censor
-- ----------------------------
DROP TABLE IF EXISTS `censor`;
CREATE TABLE `censor`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id` int(11) NOT NULL COMMENT '操作者的id',
  `domain_id` int(11) NOT NULL COMMENT '被审查的域名id',
  `outcome` int(11) NOT NULL DEFAULT 0 COMMENT '审查结果',
  `comment` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '操作原因',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '审查时间',
  PRIMARY KEY (`id`, `user_id`, `domain_id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 7 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of censor
-- ----------------------------

-- ----------------------------
-- Table structure for domain
-- ----------------------------
DROP TABLE IF EXISTS `domain`;
CREATE TABLE `domain`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
  `dom` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '一级域名',
  `is_record` int(11) NOT NULL DEFAULT 0 COMMENT '是否备案',
  `state` int(11) NOT NULL DEFAULT 0 COMMENT '状态',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 3 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of domain
-- ----------------------------

-- ----------------------------
-- Table structure for env
-- ----------------------------
DROP TABLE IF EXISTS `env`;
CREATE TABLE `env`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
  `key` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '键',
  `value` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '值',
  PRIMARY KEY (`id`, `key`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of env
-- ----------------------------

-- ----------------------------
-- Table structure for invite
-- ----------------------------
DROP TABLE IF EXISTS `invite`;
CREATE TABLE `invite`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
  `value` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '邀请码',
  `record_num` int(11) NOT NULL DEFAULT 0 COMMENT '给予的备案域名数量',
  `domain_num` int(11) NOT NULL DEFAULT 0 COMMENT '给予的非备案域名数量',
  `current` int(11) NOT NULL DEFAULT 0 COMMENT '当前邀请人数',
  `max` int(11) NOT NULL DEFAULT 0 COMMENT '最大邀请人数',
  `is_lock` int(11) NOT NULL DEFAULT 0 COMMENT '是否锁定',
  `over_time` datetime NOT NULL COMMENT '结束时间',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`, `value`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 2 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of invite
-- ----------------------------

-- ----------------------------
-- Table structure for records
-- ----------------------------
DROP TABLE IF EXISTS `records`;
CREATE TABLE `records`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
  `sub` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '子域名',
  `dom_id` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '上级域名id',
  `type` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'A' COMMENT '类型',
  `RecordId` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '解析ID',
  `user_id` int(11) NOT NULL COMMENT '用户id',
  `value` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `is_delect` int(11) NOT NULL DEFAULT 0 COMMENT '是否删除',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `audit` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '审核时间',
  PRIMARY KEY (`id`, `RecordId`, `user_id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 11 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of records
-- ----------------------------

-- ----------------------------
-- Table structure for ticket_from
-- ----------------------------
DROP TABLE IF EXISTS `ticket_from`;
CREATE TABLE `ticket_from`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
  `title` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '标题',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '更新时间',
  `is_lock` int(11) NOT NULL DEFAULT 0 COMMENT '是否锁定',
  `user_id` int(11) NOT NULL COMMENT '用户id',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 2 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of ticket_from
-- ----------------------------

-- ----------------------------
-- Table structure for tickets
-- ----------------------------
DROP TABLE IF EXISTS `tickets`;
CREATE TABLE `tickets`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
  `identity` int(11) NOT NULL COMMENT '标识码',
  `user` int(11) NOT NULL COMMENT '回复的用户id',
  `msg` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '回复的内容',
  `up_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '回复的时间',
  PRIMARY KEY (`id`, `identity`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 3 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of tickets
-- ----------------------------

-- ----------------------------
-- Table structure for user
-- ----------------------------
DROP TABLE IF EXISTS `user`;
CREATE TABLE `user`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
  `auth` int(11) NOT NULL DEFAULT 0 COMMENT '用户组',
  `username` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '用户名',
  `password` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '用户密码',
  `email` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '用户邮箱',
  `usernick` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '用户昵称',
  `ukey` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '用户登录key',
  `record_num` int(11) NOT NULL DEFAULT 0 COMMENT '额外的备案域名数量',
  `domain_num` int(11) NOT NULL DEFAULT 0 COMMENT '额外的非备案域名数量',
  `update_time` datetime NULL DEFAULT NULL COMMENT '用户上次操作时间',
  `is_lock` int(11) NOT NULL DEFAULT 0 COMMENT '用户状态',
  PRIMARY KEY (`id`, `ukey`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 4 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of user
-- ----------------------------
INSERT INTO `user` VALUES (1, 999, 'admin', '21232f297a57a5a743894a0e4a801fc3', '1109864065@qq.com', '基蒲', 'bb5c8a1b0ddf2a1a67ce89c7cc45674d', 1, 96, '2024-05-10 11:40:32', 0);
INSERT INTO `user` VALUES (2, 0, 'user', '24c9e15e52afc47c225b757e7bee1f9d', 'user@test.io', 'user', '22b94b9755da986a22fcc83964c0ea55', 0, 0, NULL, 0);
INSERT INTO `user` VALUES (3, 0, 'test', '5a105e8b9d40e1329780d62ea2265d8a', 'wzar20@163.com', 'test', '3ff74730e80cae4d5b5d98194d8abeb9', 1, 2, '2024-05-10 12:50:03', 0);

-- ----------------------------
-- View structure for all_view
-- ----------------------------
DROP VIEW IF EXISTS `all_view`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `all_view` AS select `user`.`id` AS `id`,`user`.`auth` AS `auth`,`user`.`username` AS `username`,`user`.`email` AS `email`,`user`.`usernick` AS `usernick`,`records`.`sub` AS `sub`,`records`.`RecordId` AS `RecordId`,`domain`.`dom` AS `dom`,`domain`.`sub_` AS `sub_` from ((`user` join `records`) join `domain`) where ((`user`.`id` = `records`.`id`) and (`records`.`dom_id` = `domain`.`id`));

-- ----------------------------
-- View structure for ticket_view
-- ----------------------------
DROP VIEW IF EXISTS `ticket_view`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `ticket_view` AS select `tickets`.`id` AS `id`,`tickets`.`identity` AS `identity`,`user`.`usernick` AS `usernick`,`tickets`.`msg` AS `msg`,`tickets`.`up_time` AS `up_time`,`user`.`id` AS `user_id` from (`tickets` join `user`) where (`tickets`.`user` = `user`.`id`);

-- ----------------------------
-- View structure for user_auth
-- ----------------------------
DROP VIEW IF EXISTS `user_auth`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `user_auth` AS select `user`.`id` AS `id`,`user`.`username` AS `username`,`user`.`email` AS `email`,`user`.`usernick` AS `usernick`,`auth`.`name` AS `auth`,`user`.`record_num` AS `record_num`,`user`.`domain_num` AS `domain_num`,`user`.`update_time` AS `update_time`,`user`.`is_lock` AS `is_lock` from (`auth` join `user`) where (`user`.`auth` = `auth`.`auth`);

-- ----------------------------
-- View structure for user_records
-- ----------------------------
DROP VIEW IF EXISTS `user_records`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `user_records` AS select `user`.`ukey` AS `ukey`,`records`.`sub` AS `sub`,`domain`.`dom` AS `dom`,`records`.`RecordId` AS `RecordId`,`records`.`is_delect` AS `is_delect`,`records`.`create_time` AS `create_time`,`records`.`type` AS `type`,`records`.`value` AS `value`,`domain`.`is_record` AS `is_record`,`user`.`id` AS `id`,`records`.`id` AS `dns_id`,`user`.`usernick` AS `usernick`,`records`.`audit` AS `audit` from ((`user` join `records`) join `domain`) where ((`user`.`id` = `records`.`user_id`) and (`records`.`dom_id` = `domain`.`id`));

SET FOREIGN_KEY_CHECKS = 1;
