<?php
/**
 * Plugin Class File
 *
 * Created:   December 14, 2017
 *
 * @package:  Modern Framework for Wordpress
 * @author:   Kevin Carwile
 * @since:    1.4.0
 */
namespace Modern\Wordpress\Helpers\Form\SymfonyForm;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Access denied.' );
}

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Modern\Wordpress\Helpers\Form\SymfonyForm;

/**
 * FieldgroupType Class
 */
class FieldgroupType extends AbstractType
{
    /**
     * @param OptionsResolverInterface $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefaults([
				'type' => '',
                'title' => '',
                'inherit_data' => true,
                'options' => array(),
                'label' => false,
            ]);
    }
	
    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

    }
	
    /**
     * @param FormView      $view
     * @param FormInterface $form
     * @param array         $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
		$view->vars['title'] = $options['title'];
		$view->vars['type'] = $options['type'];
    }
	
    /**
     * @return string
     */
    public function getName()
    {
        return 'fieldgroup';
    }
}
