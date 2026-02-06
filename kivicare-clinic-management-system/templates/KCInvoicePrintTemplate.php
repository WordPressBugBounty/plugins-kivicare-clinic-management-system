<?php
if (!defined('ABSPATH')) {
    exit;
}

/* Current Date & Time */
$currentDateTime = date_i18n(get_option('date_format') . ' ' . get_option('time_format'));
$invoiceDate = $appointment['appointmentStartDate'];
$appointmentId = $appointment['id'];

// Payment status styling
$paymentStatus = !empty($appointment['paymentStatus']) ? ucfirst($appointment['paymentStatus']) : 'Pending';
$paymentStatusColor = strtolower($paymentStatus) === 'paid' ? '#219653' : '#dc2626';
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title><?php echo esc_html__('Appointment Invoice', 'kivicare-clinic-management-system'); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1C1F34;
            line-height: 1.4;
            padding: 20px 40px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        h4,
        h5 {
            color: #1C1F34;
            font-weight: bold;
        }

        h4 {
            font-size: 14px;
            margin-bottom: 8px;
        }

        h5 {
            font-size: 13px;
            margin: 8px 0;
        }

        .text-gray {
            color: #6C757D;
        }

        .text-dark {
            color: #1C1F34;
        }

        .text-primary {
            color: #5F60B9;
        }

        .text-muted {
            color: #6B6B6B;
        }

        a {
            color: #5F60B9;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <!-- Header: Logo and Invoice Info -->
    <table style="margin-bottom: 16px; margin-top: 16px;">
        <tr>
            <td style="width: 50%; vertical-align: middle;">
                <?php if (!empty($clinic['logo'])): ?>
                    <img src="<?php echo esc_url($clinic['logo']); ?>" alt="<?php echo esc_attr($clinic['name']); ?>"
                        style="height: 30px;">
                <?php else: ?>
                    <strong style="font-size: 16px;"><?php echo esc_html($clinic['name']); ?></strong>
                <?php endif; ?>
            </td>
            <td style="width: 50%; text-align: right; vertical-align: middle;">
                <span style="color: #6C757D;"><?php echo esc_html__('Invoice Date:', 'kivicare-clinic-management-system'); ?></span>
                <span style="color: #1C1F34; padding-right: 20px;"><?php echo esc_html($invoiceDate); ?></span>
                <span style="color: #6C757D;"><?php echo esc_html__('Invoice ID -', 'kivicare-clinic-management-system'); ?></span>
                <span style="color: #5F60B9;"> #<?php echo esc_html($appointmentId); ?></span>
            </td>
        </tr>
    </table>
    <div style="margin: 16px 0; border-top: 1px solid #f1f5f9;"></div>

    <!-- Clinic Information -->
    <table style="margin-bottom: 40px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <h5 style="margin-top: 0;"><?php echo esc_html($clinic['name']); ?></h5>
                <p style="color: #6C757D; margin: 0;">
                    <?php echo esc_html(trim($clinic['address'] . ', ' . $clinic['city'] . ', ' . $clinic['country'] . ', ' . $clinic['postal_code'])); ?>
                </p>
            </td>
            <td style="width: 40%; text-align: right; vertical-align: top;">
                <span><?php echo esc_html__('Payment Status:', 'kivicare-clinic-management-system'); ?></span>
                <span
                    style="margin-left: 8px; background-color: <?php echo esc_attr($paymentStatusColor); ?>; color: #fff; padding: 2px 10px; border-radius: 50px;">
                    <?php echo esc_html(strtoupper($paymentStatus)); ?>
                </span>
                <p style="color: #1C1F34; margin: 0;"><?php echo esc_html($clinic['phone']); ?></p>
                <span style="color: #1C1F34;"><?php echo esc_html($clinic['email']); ?></span>
            </td>
        </tr>
    </table>

    <!-- Appointment Information -->
    <div style="margin-bottom: 40px;">

        <h5 style="margin-top: 0;"><?php echo esc_html__('Appointment Information:', 'kivicare-clinic-management-system'); ?></h5>
        <table style="border: 1px solid #ccc; margin-top: 16px; overflow: hidden; background: #F6F7F9;">
            <thead>
                <tr >
                    <th style="padding: 5px 10px; text-align: left; color: #1C1F34;">
                        <?php echo esc_html__('Appointment Date:', 'kivicare-clinic-management-system'); ?>
                    </th>
                    <th style="padding: 5px 10px; text-align: left; color: #1C1F34;">
                        <?php echo esc_html__('Appointment Time:', 'kivicare-clinic-management-system'); ?>
                    </th>
                    <th style="padding: 5px 10px; text-align: left; color: #1C1F34;">
                        <?php echo esc_html__('Appointment ID:', 'kivicare-clinic-management-system'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding: 5px 10px; text-align: left; color: #6B6B6B;"><?php echo esc_html($appointment['appointmentStartDate']); ?></td>
                    <td style="padding: 5px 10px; text-align: left; color: #6B6B6B;"><?php echo esc_html($appointment['appointmentStartTime']); ?></td>
                    <td style="padding: 5px 10px; text-align: left; color: #6B6B6B;">#<?php echo esc_html($appointmentId); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Patient, Clinic, Doctor Details - Table Based Layout -->
    <table style="margin-bottom: 40px;">
        <tr>
            <!-- Patient Detail -->
            <td style="width: 33%; vertical-align: top; padding-right: 8px;">
                <h5><?php echo esc_html__('Patient Detail:', 'kivicare-clinic-management-system'); ?></h5>
                <table style="background: #F6F7F9; padding: 8px;">
                    <tr>
                        <td style="color: #1C1F34; padding: 2px; width: 40%;"><?php echo esc_html__('Name:', 'kivicare-clinic-management-system'); ?></td>
                        <td style="color: #6B6B6B; padding: 2px;"><?php echo esc_html($patient['name']); ?></td>
                    </tr>
                    <tr>
                        <td style="color: #1C1F34; padding: 2px;"><?php echo esc_html__('Mobile Number:', 'kivicare-clinic-management-system'); ?></td>
                        <td style="color: #6B6B6B; padding: 2px;"><?php echo esc_html($patient['phone']); ?></td>
                    </tr>
                    <tr>
                        <td style="color: #1C1F34; padding: 2px;"><?php echo esc_html__('Email:', 'kivicare-clinic-management-system'); ?></td>
                        <td style="color: #6B6B6B; padding: 2px; word-break: break-word;">
                            <?php echo esc_html($patient['email']); ?>
                        </td>
                    </tr>
                </table>
            </td>

            <!-- Clinic Detail -->
            <td style="width: 33%; vertical-align: top; padding-left: 2px; padding-right: 2px;">
                <h5><?php echo esc_html__('Clinic Detail:', 'kivicare-clinic-management-system'); ?></h5>
                <table style="background: #F6F7F9; padding: 8px;">
                    <tr>
                        <td style="color: #1C1F34; padding: 2px; width: 40%;"><?php echo esc_html__('Name:', 'kivicare-clinic-management-system'); ?></td>
                        <td style="color: #6B6B6B; padding: 2px;"><?php echo esc_html($clinic['name']); ?></td>
                    </tr>
                    <tr>
                        <td style="color: #1C1F34; padding: 2px;"><?php echo esc_html__('Mobile Number:', 'kivicare-clinic-management-system'); ?></td>
                        <td style="color: #6B6B6B; padding: 2px;"><?php echo esc_html($clinic['phone']); ?></td>
                    </tr>
                    <tr>
                        <td style="color: #1C1F34; padding: 2px;"><?php echo esc_html__('Email:', 'kivicare-clinic-management-system'); ?></td>
                        <td style="color: #6B6B6B; padding: 2px; word-break: break-word;">
                            <?php echo esc_html($clinic['email']); ?>
                        </td>
                    </tr>
                </table>
            </td>

            <!-- Doctor Detail -->
            <td style="width: 33%; vertical-align: top; padding-left: 8px;">
                <h5><?php echo esc_html__('Doctor Detail:', 'kivicare-clinic-management-system'); ?></h5>
                <table style="background: #F6F7F9; padding: 8px;">
                    <tr>
                        <td style="color: #1C1F34; padding: 2px; width: 40%;"><?php echo esc_html__('Name:', 'kivicare-clinic-management-system'); ?></td>
                        <td style="color: #6B6B6B; padding: 2px;"><?php echo esc_html($doctor['name']); ?></td>
                    </tr>
                    <tr>
                        <td style="color: #1C1F34; padding: 2px;"><?php echo esc_html__('Specialization:', 'kivicare-clinic-management-system'); ?></td>
                        <td style="color: #6B6B6B; padding: 2px;"><?php echo esc_html($doctor['specialization']); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="color: #1C1F34; padding: 2px;"><?php echo esc_html__('Email:', 'kivicare-clinic-management-system'); ?></td>
                        <td style="color: #6B6B6B; padding: 2px; word-break: break-word;">
                            <?php echo esc_html($clinic['email']); ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Services Table -->
    <table style="border: 1px solid #ccc; margin-top: 16px;">
        <thead>
            <tr style="background: #F6F7F9;">
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ccc;">
                    <?php echo esc_html__('Services', 'kivicare-clinic-management-system'); ?>
                </th>
                <th style="padding: 12px; text-align: right; border-bottom: 1px solid #ccc;">
                    <?php echo esc_html__('Price', 'kivicare-clinic-management-system'); ?>
                </th>
                <th style="padding: 12px; text-align: right; border-bottom: 1px solid #ccc;">
                    <?php echo esc_html__('Amount', 'kivicare-clinic-management-system'); ?>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($services)): ?>
                <?php foreach ($services as $service): ?>
                    <tr>
                        <td style="padding: 12px; text-align: left;"><?php echo esc_html($service['name']); ?></td>
                        <td style="padding: 12px; text-align: right;">
                            <?php echo esc_html($currency_prefix . number_format($service['charges'], 2) . $currency_postfix); ?>
                        </td>
                        <td style="padding: 12px; text-align: right;">
                            <?php echo esc_html($currency_prefix . number_format($service['charges'], 2) . $currency_postfix); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php if (!empty($tax_items['tax_data'])): ?>
        <table class="services" style="border: 1px solid #ccc; margin-top: 16px;">
            <thead>
            <tr style="background: #F6F7F9;">
                <th width="10%"  style="padding: 12px; text-align: left; border-bottom: 1px solid #ccc;">Sr No.</th>
                <th width="70%"  style="padding: 12px; text-align: right; border-bottom: 1px solid #ccc;">Tax Name</th>
                <th width="20%"  style="padding: 12px; text-align: right; border-bottom: 1px solid #ccc;">Charges</th>
            </tr>
            </thead>
            <tbody>
            <?php
                foreach ($tax_items['tax_data'] as $i => $tax):
            ?>
                <tr>
                    <td style="padding: 12px; text-align: left;"><?php echo esc_html($i + 1); ?></td>
                    <td style="padding: 12px; text-align: right;"><?php echo esc_html($tax['tax_name'] ?? 'Tax'); ?></td>
                    <td style="padding: 12px; text-align: right;"><?php 
                        echo esc_html(($currency_prefix ?? '') . number_format((float)$tax['tax_amount'], 2) . ($currency_postfix ?? ''));; 
                    ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php
    $tax_total = 0;
    foreach ($tax_items['tax_data'] as $tax) {
        $tax_total += (float)($tax['tax_amount'] ?? 0);
    }
    $grand_total += $total_charges + $tax_total;
    ?>

    <!-- Summary Table -->
    <table style="margin-top: 16px;">
        <tbody style="background: #F6F7F9;">
            <tr>
                <td style="padding: 12px; width: 70%;"></td>
                <td style="padding: 12px; text-align: left; color: #6B6B6B; ">
                    <?php echo esc_html__('Sub Total:', 'kivicare-clinic-management-system'); ?>
                </td>
                <td style="padding: 12px; text-align: right; color: #1C1F34; ">
                    <?php echo esc_html($currency_prefix . $total_charges . $currency_postfix); ?>
                </td>
            </tr>
            <?php
                if ($tax_total > 0):
            ?>
            <tr>
                <td style="padding: 12px; width: 70%;"></td>
                <td style="padding: 12px; text-align: left; color: #6B6B6B; ">
                    <?php echo esc_html__('Total Tax:', 'kivicare-clinic-management-system'); ?>
                </td>
                <td style="padding: 12px; text-align: right; color: #1C1F34; ">
                    <?php echo esc_html($currency_prefix . $tax_total . $currency_postfix); ?>
                </td>
            </tr>
            <?php
                endif;
            ?>
            <tr>
                <td style="padding: 12px; width: 70%;"></td>
                <td style="padding: 12px; text-align: left; color: #6B6B6B; border-top: 1px solid #ccc;">
                    <?php echo esc_html__('Total Amount:', 'kivicare-clinic-management-system'); ?>
                </td>
                <td style="padding: 12px; text-align: right; color: #1C1F34; border-top: 1px solid #ccc;">
                    <?php echo esc_html($currency_prefix . $grand_total . $currency_postfix); ?>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Documents Section -->
    <?php if (!empty($appointmentReport) && is_array($appointmentReport)): ?>
        <div style="margin-top: 24px;">
            <h4><?php echo esc_html__('Attached Documents', 'kivicare-clinic-management-system'); ?></h4>
            <table style="border: 1px solid #ccc; margin-top: 8px;">
                <thead>
                    <tr style="background: #F6F7F9;">
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ccc;">
                            <?php echo esc_html__("Filename", "kivicare-clinic-management-system"); ?>
                        </th>
                        <th style="padding: 12px; text-align: right; border-bottom: 1px solid #ccc;">
                            <?php echo esc_html__("Action", "kivicare-clinic-management-system"); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointmentReport as $report): ?>
                        <tr>
                            <td style="padding: 12px;"><?php echo esc_html($report['filename']); ?></td>
                            <td style="padding: 12px; text-align: right;">
                                <a href="<?php echo esc_url($report['url']); ?>"
                                    style="padding: 5px 10px; background-color: #5F60B9; color: white; border-radius: 2px;">
                                    <?php echo esc_html__('View', 'kivicare-clinic-management-system'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</body>

</html>