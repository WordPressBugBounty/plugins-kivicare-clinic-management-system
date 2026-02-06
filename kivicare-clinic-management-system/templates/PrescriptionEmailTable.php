<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/**
 * Prescription Email Table Template
 * 
 * @var array $prescriptions List of prescription objects
 */
?>
<table border="1" cellpadding="10" cellspacing="0" style="width:100%; border-collapse:collapse;">
    <tr style="background-color:#f2f2f2;">
        <th><?php echo esc_html__('NAME', 'kivicare-clinic-management-system'); ?></th>
        <th><?php echo esc_html__('FREQUENCY', 'kivicare-clinic-management-system'); ?></th>
        <th><?php echo esc_html__('DAYS', 'kivicare-clinic-management-system'); ?></th>
        <th><?php echo esc_html__('Instruction', 'kivicare-clinic-management-system'); ?></th>
    </tr>
    <?php foreach ($prescriptions as $p): ?>
        <tr>
            <td><?php echo esc_html($p->name); ?></td>
            <td><?php echo esc_html($p->frequency); ?></td>
            <td><?php echo esc_html($p->duration); ?></td>
            <td><?php echo esc_html($p->instruction); ?></td>
        </tr>
    <?php endforeach; ?>
</table>
