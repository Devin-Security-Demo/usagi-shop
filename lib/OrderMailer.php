<?php

declare(strict_types=1);

namespace Usagi;

use PHPMailer;

final class OrderMailer
{
    public function __construct(private array $config)
    {
    }

    public function sendConfirmation(array $order, array $lines, string $toEmail, string $toName): bool
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $this->config['host'];
        $mail->Port = (int) $this->config['port'];
        $mail->SMTPAuth = false;
        $mail->SMTPAutoTLS = false;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->setFrom($this->config['from'], $this->config['from_name']);
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = '【うさぎ商事】ご注文ありがとうございます（注文番号 ' . $order['order_no'] . '）';
        $mail->Body = $this->body($order, $lines, $toName);

        try {
            return $mail->send();
        } catch (\phpmailerException $e) {
            error_log('order mail failed: ' . $e->getMessage());
            return false;
        }
    }

    private function body(array $order, array $lines, string $name): string
    {
        $rows = '';
        foreach ($lines as $line) {
            $rows .= sprintf("・%s × %d  ¥%s\n", $line['name'], $line['qty'], number_format($line['line_total']));
        }
        return <<<TXT
{$name} 様

このたびは うさぎ商事オンラインショップ をご利用いただき、誠にありがとうございます。
以下の内容でご注文を承りました。

■ 注文番号：{$order['order_no']}

{$rows}
小計　　：¥{$this->n($order['subtotal'])}
送料　　：¥{$this->n($order['shipping_fee'])}
合計　　：¥{$this->n($order['total'])}（税込）

■ お届け先
〒{$order['ship_zip']} {$order['ship_address']}
{$order['ship_name']} 様

商品の発送準備が整いましたら、あらためてご連絡いたします。

──────────────────────
株式会社うさぎ商事 オンラインショップ
shop@usagi.example
──────────────────────
TXT;
    }

    private function n(int|string $v): string
    {
        return number_format((int) $v);
    }
}
