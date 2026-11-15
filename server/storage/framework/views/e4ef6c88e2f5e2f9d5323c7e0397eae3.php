<?php $__env->startSection('code', '404'); ?>
<?php $__env->startSection('icon', 'search'); ?>
<?php $__env->startSection('title', __('Page not found')); ?>
<?php $__env->startSection('message', __('This page does not exist, or the topic was renamed or deleted. Check the address, or look for the topic from the dashboard.')); ?>


<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/errors/404.blade.php ENDPATH**/ ?>