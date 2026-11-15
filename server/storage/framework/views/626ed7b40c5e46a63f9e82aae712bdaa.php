<?php $__env->startSection('code', (string) $exception->getStatusCode()); ?>
<?php $__env->startSection('icon', 'alert'); ?>
<?php $__env->startSection('title', __('This request cannot be completed')); ?>
<?php $__env->startSection('message', __('Check the address, then try again.')); ?>


<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/lkaopremier/Dev/carillon/server/resources/views/errors/4xx.blade.php ENDPATH**/ ?>