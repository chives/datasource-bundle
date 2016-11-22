<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FSi\Bundle\DataSourceBundle\Tests\Extension\Symfony;

use FSi\Bundle\DataSourceBundle\DataSource\Extension\Symfony\Form\Extension\DatasourceExtension;
use FSi\Bundle\DataSourceBundle\DataSource\Extension\Symfony\Form\Driver\DriverExtension;
use FSi\Bundle\DataSourceBundle\DataSource\Extension\Symfony\Form\EventSubscriber\Events;
use FSi\Bundle\DataSourceBundle\DataSource\Extension\Symfony\Form\Field\FormFieldExtension;
use FSi\Bundle\DataSourceBundle\DataSource\Extension\Symfony\Form\Type\BetweenType;
use FSi\Bundle\DataSourceBundle\Tests\Fixtures\Form as TestForm;
use FSi\Component\DataSource\Field\FieldAbstractExtension;
use FSi\Component\DataSource\Field\FieldView;
use ReflectionMethod;
use Symfony\Component\Form;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\DefaultCsrfProvider;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Security;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use FSi\Component\DataSource\DataSourceInterface;
use FSi\Component\DataSource\Event\FieldEvent;
use FSi\Component\DataSource\Event\DataSourceEvent\ViewEventArgs;

/**
 * Tests for Symfony Form Extension.
 */
class FormExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Provides types.
     *
     * @return array
     */
    public static function typesProvider()
    {
        return array(
            array('text'),
            array('number'),
            array('date'),
            array('time'),
            array('datetime'),
        );
    }

    /**
     * Provides field types, comparison types and expected form input types.
     *
     * @return array
     */
    public static function fieldTypesProvider()
    {
        return array(
            array('text', 'isNull', 'choice'),
            array('text', 'eq', 'text'),
            array('number', 'isNull', 'choice'),
            array('number', 'eq', 'text'),
            array('datetime', 'isNull', 'choice'),
            array('datetime', 'eq', 'datetime'),
            array('datetime', 'between', 'datasource_between'),
            array('time', 'isNull', 'choice'),
            array('time', 'eq', 'time'),
            array('date', 'isNull', 'choice'),
            array('date', 'eq', 'date'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        if (!class_exists('Symfony\Component\Form\Form')) {
            $this->markTestSkipped('Symfony Form needed!');
        }
    }

    /**
     * Returns mock of FormFactory.
     *
     * @return object
     */
    private function getFormFactory()
    {
        $typeFactory = new Form\ResolvedFormTypeFactory();
        $typeFactory->createResolvedType(new BetweenType(), array());

        if (version_compare(Kernel::VERSION, '3.0.0', '>=')) {
            $tokenManager = new CsrfTokenManager();
        } else {
            $tokenManager = new DefaultCsrfProvider('tests');
        }

        $registry = new Form\FormRegistry(
            array(
                new TestForm\Extension\TestCore\TestCoreExtension(),
                new Form\Extension\Core\CoreExtension(),
                new Form\Extension\Csrf\CsrfExtension($tokenManager),
                new DatasourceExtension()
            ),
            $typeFactory
        );
        return new Form\FormFactory($registry, $typeFactory);
    }

    /**
     * Checks creation of DriverExtension.
     */
    public function testCreateDriverExtension()
    {
        $formFactory = $this->getFormFactory();
        $translator = $this->getMock('Symfony\Component\Translation\TranslatorInterface');

        $extension = new DriverExtension($formFactory, $translator);
    }

    /**
     * Tests if driver extension has all needed fields.
     */
    public function testDriverExtension()
    {
        $formFactory = $this->getFormFactory();
        $translator = $this->getMock('Symfony\Component\Translation\TranslatorInterface');
        $extension = new DriverExtension($formFactory, $translator);

        $this->assertTrue($extension->hasFieldTypeExtensions('text'));
        $this->assertTrue($extension->hasFieldTypeExtensions('number'));
        $this->assertTrue($extension->hasFieldTypeExtensions('entity'));
        $this->assertTrue($extension->hasFieldTypeExtensions('date'));
        $this->assertTrue($extension->hasFieldTypeExtensions('time'));
        $this->assertTrue($extension->hasFieldTypeExtensions('datetime'));
        $this->assertFalse($extension->hasFieldTypeExtensions('wrong'));

        $extension->getFieldTypeExtensions('text');
        $extension->getFieldTypeExtensions('number');
        $extension->getFieldTypeExtensions('entity');
        $extension->getFieldTypeExtensions('date');
        $extension->getFieldTypeExtensions('time');
        $extension->getFieldTypeExtensions('datetime');
        $this->setExpectedException('FSi\Component\DataSource\Exception\DataSourceException');
        $extension->getFieldTypeExtensions('wrong');
    }

    public function testFormOrder()
    {
        $self = $this;

        $datasource = $this->getMock('FSi\Component\DataSource\DataSourceInterface');
        $view = $this->getMock('FSi\Component\DataSource\DataSourceViewInterface');

        $fields = array();
        $fieldViews = array();
        for ($i = 0; $i < 15; $i++) {
            $field = $this->getMock('FSi\Component\DataSource\Field\FieldTypeInterface');
            $fieldView = $this->getMock('FSi\Component\DataSource\Field\FieldViewInterface');

            unset($order);
            if ($i < 5) {
                $order = -4 + $i;
            } else if ($i > 10) {
                $order = $i - 10;
            }

            $field
                ->expects($this->any())
                ->method('getName')
                ->will($this->returnValue('field' . $i))
            ;

            $field
                ->expects($this->any())
                ->method('hasOption')
                ->will($this->returnValue(isset($order)))
            ;

            if (isset($order)) {
                $field
                    ->expects($this->any())
                    ->method('getOption')
                    ->will($this->returnValue($order))
                ;
            }

            $fieldView
                ->expects($this->any())
                ->method('getName')
                ->will($this->returnValue('field' . $i))
            ;

            $fields['field' . $i] = $field;
            $fieldViews['field' . $i] = $fieldView;
            if (isset($order)) {
                $names['field' . $i] = $order;
            } else {
                $names['field' . $i] = null;
            }
        }

        $datasource
            ->expects($this->any())
            ->method('getField')
            ->will($this->returnCallback(function($field) use ($fields) { return $fields[$field]; }))
        ;

        $view
            ->expects($this->any())
            ->method('getFields')
            ->will($this->returnValue($fieldViews))
        ;

        $view
            ->expects($this->once())
            ->method('setFields')
            ->will($this->returnCallback(function(array $fields) use ($self) {
                $names = array();
                foreach ($fields as $field) {
                    $names[] = $field->getName();
                }

                $self->assertSame(
                    array(
                        'field0', 'field1', 'field2', 'field3', 'field5',
                        'field6', 'field7', 'field8', 'field9', 'field10', 'field4',
                        'field11', 'field12', 'field13', 'field14'
                    ),
                    $names
                );
            }))
        ;

        $event = new ViewEventArgs($datasource, $view);
        $subscriber = new Events();
        $subscriber->postBuildView($event);
    }

    /**
     * Checks fields behaviour.
     *
     * @dataProvider typesProvider
     */
    public function testFields($type)
    {
        $self = $this;
        $formFactory = $this->getFormFactory();
        $translator = $this->getMock('Symfony\Component\Translation\TranslatorInterface');
        $extension = new DriverExtension($formFactory, $translator);
        $field = $this->getMock('FSi\Component\DataSource\Field\FieldTypeInterface');
        $driver = $this->getMock('FSi\Component\DataSource\Driver\DriverInterface');
        $datasource = $this->getMock('FSi\Component\DataSource\DataSource', array(), array($driver));

        $datasource
            ->expects($this->any())
            ->method('getName')
            ->will($this->returnValue('datasource'))
        ;

        $field
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('name'))
        ;

        $field
            ->expects($this->any())
            ->method('getDataSource')
            ->will($this->returnValue($datasource))
        ;

        $field
            ->expects($this->any())
            ->method('getType')
            ->will($this->returnValue($type))
        ;

        $field
            ->expects($this->any())
            ->method('hasOption')
            ->will($this->returnValue(false)/*$this->returnCallback(function($option) use ($type) {
                return (($type == 'number') && ($option =='form_type'));
            })*/)
        ;

        $field
            ->expects($this->any())
            ->method('getOption')
            ->will($this->returnCallback(function($option) use ($type) {
                switch ($option) {
                    case 'form_filter':
                        return true;
                    case 'form_type':
/*                        if ($type == 'number') {
                            return 'text';
                        } else {*/
                            return null;
//                        }
                    case 'form_from_options':
                    case 'form_to_options':
                    case 'form_options':
                        return array();
                }
            }))
        ;

        $extensions = $extension->getFieldTypeExtensions($type);

        if ($type == 'datetime') {
            $parameters = array('datasource' => array(DataSourceInterface::PARAMETER_FIELDS => array('name' =>
                array(
                    'date' => array('year' => 2012, 'month' => 12, 'day' => 12),
                    'time' => array('hour' => 12, 'minute' => 12),
                ),
            )));
            $parameters2 = array('datasource' => array(DataSourceInterface::PARAMETER_FIELDS => array('name' => new \DateTime('2012-12-12 12:12:00'))));
        } elseif ($type == 'time') {
            $parameters = array('datasource' => array(DataSourceInterface::PARAMETER_FIELDS => array('name' =>
                array(
                    'hour' => 12,
                    'minute' => 12,
                ),
            )));
            $parameters2 = array('datasource' => array(DataSourceInterface::PARAMETER_FIELDS => array('name' => new \DateTime(date('Y-m-d', 0).' 12:12:00'))));
        } elseif ($type == 'date') {
            $parameters = array('datasource' => array(DataSourceInterface::PARAMETER_FIELDS => array('name' =>
                array(
                    'year' => 2012,
                    'month' => 12,
                    'day' => 12,
                ),
            )));
            $parameters2 = array('datasource' => array(DataSourceInterface::PARAMETER_FIELDS => array('name' => new \DateTime('2012-12-12'))));
        } elseif ($type == 'number') {
            $parameters = array('datasource' => array(DataSourceInterface::PARAMETER_FIELDS => array('name' => 123)));
            $parameters2 = $parameters;
        } else {
            $parameters = array('datasource' => array(DataSourceInterface::PARAMETER_FIELDS => array('name' => 'value')));
            $parameters2 = $parameters;
        }

        $args = new FieldEvent\ParameterEventArgs($field, $parameters);
        foreach ($extensions as $ext) {
            $this->assertTrue($ext instanceof FieldAbstractExtension);
            $ext->preBindParameter($args);
        }
        $parameters = $args->getParameter();
        $this->assertEquals($parameters2, $parameters);
        $fieldView = $this->getMock('FSi\Component\DataSource\Field\FieldViewInterface', array(), array($field));

        $fieldView
            ->expects($this->atLeastOnce())
            ->method('setAttribute')
            ->will($this->returnCallback(function ($attribute, $value) use ($self, $type) {
                if ($attribute == 'form') {
                    $self->assertInstanceOf('\Symfony\Component\Form\FormView', $value);
                }
            }))
        ;

        $args = new FieldEvent\ViewEventArgs($field, $fieldView);
        foreach ($extensions as $ext) {
            $ext->postBuildView($args);
        }
    }

    /**
     * Checks types of generated fields
     *
     * @dataProvider fieldTypesProvider
     */
    public function testFormFields($type, $comparison, $expected)
    {
        $formFactory = $this->getFormFactory();
        $translator = $this->getMock('Symfony\Component\Translation\TranslatorInterface');
        $translator->expects($this->any())
            ->method('trans')
            ->will($this->returnCallback(function ($id, array $params, $translation_domain) {
                if ($translation_domain != 'DataSourceBundle') {
                    throw new \RuntimeException(sprintf('Unknown translation domain %s', $translation_domain));
                }
                switch ($id) {
                    case 'datasource.form.choices.is_null':
                        return 'is_null_translated';
                    case 'datasource.form.choices.is_not_null':
                        return 'is_not_null_translated';
                    case 'datasource.form.choices.yes':
                        return 'yes_translated';
                    case 'datasource.form.choices.no':
                        return 'no_translated';
                    default:
                        throw new \RuntimeException(sprintf('Unknown translation id %s', $id));
                }
            }));
        $extension = new DriverExtension($formFactory, $translator);
        $field = $this->getMock('FSi\Component\DataSource\Field\FieldTypeInterface');
        $driver = $this->getMock('FSi\Component\DataSource\Driver\DriverInterface');
        $datasource = $this->getMock('FSi\Component\DataSource\DataSource', array(), array($driver));

        $datasource
            ->expects($this->any())
            ->method('getName')
            ->will($this->returnValue('datasource'))
        ;

        $field
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('name'))
        ;

        $field
            ->expects($this->any())
            ->method('getDataSource')
            ->will($this->returnValue($datasource))
        ;

        $field
            ->expects($this->any())
            ->method('getType')
            ->will($this->returnValue($type))
        ;

        $field
            ->expects($this->any())
            ->method('hasOption')
            ->will($this->returnCallback(function($option) use ($type) {
                return (($type == 'number') && ($option =='form_type'));
            }))
        ;

        $field
            ->expects($this->any())
            ->method('getComparison')
            ->will($this->returnValue($comparison))
        ;

        $field
            ->expects($this->any())
            ->method('getOption')
            ->will($this->returnCallback(function($option) use ($type) {
                switch ($option) {
                    case 'form_null_value':
                        return 'empty';

                    case 'form_not_null_value':
                        return 'not empty';

                    case 'form_filter':
                        return true;

                    case 'form_type':
/*                        if ($type == 'number') {
                            return 'text';
                        } else {*/
                            return null;
//                        }

                    case 'form_from_options':
                    case 'form_to_options':
                    case 'form_options':
                        return array();
                }
            }))
        ;
        $extensions = $extension->getFieldTypeExtensions($type);

        $parameters = array('datasource' => array(DataSourceInterface::PARAMETER_FIELDS => array('name' =>
            'null'
        )));

        $args = new FieldEvent\ParameterEventArgs($field, $parameters);

        $view = new FieldView($field);
        $viewEventArgs = new FieldEvent\ViewEventArgs($field, $view);

        foreach ($extensions as $ext) {
            $ext->preBindParameter($args);
            $ext->postBuildView($viewEventArgs);
        }

        $form = $viewEventArgs->getView()->getAttribute('form');

        $this->assertEquals($expected, $form['fields']['name']->vars['block_prefixes'][1]);

        if ($comparison == 'isNull') {
            $this->assertEquals(
                'is_null_translated',
                $form['fields']['name']->vars['choices'][0]->label
            );
            $this->assertEquals(
                'is_not_null_translated',
                $form['fields']['name']->vars['choices'][1]->label
            );
        }
    }
    public function testBuildBooleanFormWhenOptionsProvided()
    {
        $formFactory = $this->getMockBuilder('Symfony\Component\Form\FormFactory')
            ->disableOriginalConstructor()
            ->getMock();
        $translator = $this->getMock('Symfony\Component\Translation\TranslatorInterface');
        $translator->expects($this->any())
            ->method('trans')
            ->will($this->returnCallback(function ($id, array $params, $translation_domain) {
                if ($translation_domain != 'DataSourceBundle') {
                    throw new \RuntimeException(sprintf('Unknown translation domain %s', $translation_domain));
                }
                switch ($id) {
                    case 'datasource.form.choices.is_null':
                        return 'is_null_translated';
                    case 'datasource.form.choices.is_not_null':
                        return 'is_not_null_translated';
                    case 'datasource.form.choices.yes':
                        return 'yes_translated';
                    case 'datasource.form.choices.no':
                        return 'no_translated';
                    default:
                        throw new \RuntimeException(sprintf('Unknown translation id %s', $id));
                }
            }));

        $formFieldExtension = new FormFieldExtension($formFactory, $translator);

        $method = new ReflectionMethod(
            'FSi\Bundle\DataSourceBundle\DataSource\Extension\Symfony\Form\Field\FormFieldExtension',
            'buildBooleanForm'
        );
        $method->setAccessible(true);

        $field = $this->getMock('FSi\Component\DataSource\Driver\Collection\Extension\Core\Field\Boolean');

        $field->expects($this->once())
            ->method('getname')
            ->will($this->returnValue('name'));

        $form = $this->getMockBuilder('Symfony\Component\Form\Form')
            ->disableOriginalConstructor()
            ->getMock();

        $expectedOptions = array(
            'choices' => array(
                '1' => 'tak',
                '0' => 'nie',
            ),
            'multiple' => false,
        );
        if (method_exists('Symfony\Component\Form\FormTypeInterface', 'configureOptions')) {
            $expectedOptions['placeholder'] = '';
        } else {
            $expectedOptions['empty_value'] = '';
        }

        $form->expects($this->exactly(1))->method('add')
            ->with(
                $this->equalTo('name'),
                $this->equalTo(
                    version_compare(Kernel::VERSION, '3.0.0', '>=')
                        ? 'Symfony\Component\Form\Extension\Core\Type\ChoiceType'
                        : 'choice'
                ),
                $this->equalTo($expectedOptions)
            );

        $options =  array(
            'choices' => array(
                '1' => 'tak',
                '0' => 'nie'
            )
        );

        $method->invoke(
            $formFieldExtension,
            $form,
            $field,
            $options
        );
    }

    public function testBuildBooleanFormWhenOptionsNotProvided()
    {
        $formFactory = $this->getMockBuilder('Symfony\Component\Form\FormFactory')
            ->disableOriginalConstructor()
            ->getMock();

        $translator = $this->getMock('Symfony\Component\Translation\TranslatorInterface');
        $translator->expects($this->any())
            ->method('trans')
            ->will($this->returnCallback(function ($id, array $params, $translation_domain) {
                if ($translation_domain != 'DataSourceBundle') {
                    throw new \RuntimeException(sprintf('Unknown translation domain %s', $translation_domain));
                }
                switch ($id) {
                    case 'datasource.form.choices.is_null':
                        return 'is_null_translated';
                    case 'datasource.form.choices.is_not_null':
                        return 'is_not_null_translated';
                    case 'datasource.form.choices.yes':
                        return 'yes_translated';
                    case 'datasource.form.choices.no':
                        return 'no_translated';
                    default:
                        throw new \RuntimeException(sprintf('Unknown translation id %s', $id));
                }
            }));

        $formFieldExtension = new FormFieldExtension($formFactory, $translator);

        $method = new ReflectionMethod(
            'FSi\Bundle\DataSourceBundle\DataSource\Extension\Symfony\Form\Field\FormFieldExtension',
            'buildBooleanForm'
        );
        $method->setAccessible(true);

        $field = $this->getMock('FSi\Component\DataSource\Driver\Collection\Extension\Core\Field\Boolean');

        $field->expects($this->once())
            ->method('getname')
            ->will($this->returnValue('name'));

        $form = $this->getMockBuilder('Symfony\Component\Form\Form')
            ->disableOriginalConstructor()
            ->getMock();

        $expectedOptions = array(
            'choices' => array(
                '1' => 'yes_translated',
                '0' => 'no_translated',
            ),
            'multiple' => false,
        );
        if (method_exists('Symfony\Component\Form\FormTypeInterface', 'configureOptions')) {
            $expectedOptions['choices'] = array_flip($expectedOptions['choices']);
            $expectedOptions['placeholder'] = '';
        } else {
            $expectedOptions['empty_value'] = '';
        }

        $form->expects($this->exactly(1))->method('add')
            ->with(
                $this->equalTo('name'),
                $this->equalTo(
                    version_compare(Kernel::VERSION, '3.0.0', '>=')
                        ? 'Symfony\Component\Form\Extension\Core\Type\ChoiceType'
                        : 'choice'
                ),
                $this->equalTo($expectedOptions)
            );

        $options =  array();

        $method->invoke(
            $formFieldExtension,
            $form,
            $field,
            $options
        );
    }
}
