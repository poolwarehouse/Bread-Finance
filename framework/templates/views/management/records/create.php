<?php
/**
 * Plugin HTML Template
 *
 * Created:  December 13, 2017
 *
 * @package  Modern Framework for Wordpress
 * @author   Kevin Carwile
 * @since    1.4.0
 *
 * @param	Plugin		$this		The plugin instance which is loading this template
 *
 * @param	Modern\Wordpress\Helpers\Form						$form			The form that was built
 * @param	Modern\Wordpress\Plugin								$plugin			The plugin that created the controller
 * @param	Modern\Wordpress\Helpers\ActiveRecordController		$controller		The active record controller
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Access denied.' );
}

?>

<div class="wrap">
	<h1><?php echo $title ?></h1>
	<?php echo $form->render() ?>
</div>