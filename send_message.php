<?php

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $botToken = $_POST["bot_token"] ?? '';
    $chatId = $_POST["chat_id"] ?? '';
    $messageType = $_POST["message_type"] ?? 'text';
    $text = $_POST["text"] ?? '';
    $buttons = json_decode($_POST["reply_markup"] ?? '', true);

    if (!$botToken || !$chatId) {
        echo json_encode(["ok" => false, "error" => "缺少 Bot Token 或 Chat ID"]);
        exit;
    }

    $url = "https://api.telegram.org/bot$botToken/";

    $payload = [
        "chat_id" => $chatId,
        "parse_mode" => "HTML",
        "caption" => $text,
    ];

 if ($buttons) {
    // 读取 inline_keyboard 并调整为两列布局
    $inlineKeyboard = $buttons['inline_keyboard'] ?? [];
    $formattedKeyboard = [];
    $row = [];

    foreach ($inlineKeyboard as $buttonRow) {
        foreach ($buttonRow as $button) {
            $row[] = $button; // 添加按钮到当前行
            if (count($row) === 2) { // 每行最多两列
                $formattedKeyboard[] = $row;
                $row = [];
            }
        }
    }

    // 如果最后一行不足两列，也需要添加
    if (!empty($row)) {
        $formattedKeyboard[] = $row;
    }

    // 更新 inline_keyboard 并生成 reply_markup
    $buttons['inline_keyboard'] = $formattedKeyboard;
    $payload["reply_markup"] = json_encode($buttons);
}


    $fileField = null;
    if (isset($_FILES["file"]) && $_FILES["file"]["error"] === UPLOAD_ERR_OK) {
        $fileType = mime_content_type($_FILES["file"]["tmp_name"]);
        $fileName = $_FILES["file"]["name"];
        
        if (in_array($fileType, ["image/gif"])) {
            // GIF 动画
            $url .= "sendAnimation";
            $fileField = "animation";
        } elseif (in_array($fileType, ["video/mp4"])) {
            // 视频
            $url .= "sendVideo";
            $fileField = "video";
        } elseif (in_array($fileType, ["application/zip"])) {
            // ZIP 文件
            $url .= "sendDocument";
            $fileField = "document";
        } elseif (in_array($fileType, ["image/jpeg", "image/png"])) {
            // 图片
            $url .= "sendPhoto";
            $fileField = "photo";
        } else {
            echo json_encode(["ok" => false, "error" => "不支持的文件类型"]);
            exit;
        }

        $payload[$fileField] = new CURLFile(
            $_FILES["file"]["tmp_name"],
            $_FILES["file"]["type"],
            $fileName
        );
    } else {
        echo json_encode(["ok" => false, "error" => "文件上传失败或不支持的消息类型"]);
        exit;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo json_encode(["ok" => false, "error" => curl_error($ch)]);
    } else {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode >= 200 && $httpCode < 300) {
            echo $response;
        } else {
            echo json_encode(["ok" => false, "error" => "Telegram API 错误", "response" => $response]);
        }
    }

    curl_close($ch);
}
