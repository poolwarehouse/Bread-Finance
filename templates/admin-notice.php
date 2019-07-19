<?php
$cssClasses = array( 'notice', 'notice-' . $type );

if ( $dismissible ) {
	$cssClasses[] = 'is-dismissible';
}
?>
<div class="<?php echo implode( ' ', $cssClasses ) ?>">
    <p><?php echo $message ?></p>
</div>
