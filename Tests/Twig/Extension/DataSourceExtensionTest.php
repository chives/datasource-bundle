<?php

/**
 * (c) FSi Sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FSi\Bundle\DataSourceBundle\Tests\Twig\Extension;

use FSi\Bundle\DataSourceBundle\Twig\Extension\DataSourceExtension;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRenderer;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Bridge\Twig\Tests\Extension\Fixtures\StubTranslator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * @author Stanislav Prokopov <stanislav.prokopov@gmail.com>
 */
class DataSourceExtensionTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \Twig_Environment
     */
    protected $twig;

    /**
     * @var DataSourceExtension
     */
    protected $extension;

    public function setUp()
    {
        $subPath = version_compare(Kernel::VERSION, '2.7.0', '<') ? 'Symfony/Bridge/Twig/' : '';
        $loader = new \Twig_Loader_Filesystem(array(
            __DIR__ . '/../../../vendor/symfony/twig-bridge/' . $subPath . 'Resources/views/Form',
            __DIR__ . '/../../../Resources/views', // datasource base theme
        ));

        $twig = new \Twig_Environment($loader);
        $twig->addExtension(new TranslationExtension(new StubTranslator()));
        $twig->addExtension($this->getFormExtension($subPath === ''));
        $twig->addGlobal('global_var', 'global_value');
        $this->twig = $twig;

        $this->extension = new DataSourceExtension($this->getContainer(), 'datasource.html.twig');
    }

    /**
     * @expectedException \Twig_Error_Loader
     * @expectedExceptionMessage Unable to find template "this_is_not_valid_path.html.twig"
     */
    public function testInitRuntimeShouldThrowExceptionBecauseNotExistingTheme()
    {
        $this->twig->addExtension(new DataSourceExtension($this->getContainer(), 'this_is_not_valid_path.html.twig'));
        // force initRuntime()
        $this->twig->loadTemplate('datasource.html.twig');
    }

    public function testInitRuntimeWithValidPathToTheme()
    {
        $this->twig->addExtension($this->extension);
        // force initRuntime()
        $this->twig->loadTemplate('datasource.html.twig');
    }

    public function testDataSourceFilterCount()
    {
        $this->twig->addExtension($this->extension);
        // force initRuntime()
        $this->twig->loadTemplate('datasource.html.twig');

        $datasourceView = $this->getDataSourceView('datasource');
        $fieldView1 = $this->getMock(
            'FSi\Component\DataSource\Field\FieldViewInterface',
            array(
                '__construct', 'getName', 'getType', 'getComparison', 'getParameter',
                'getDataSourceView', 'setDataSourceView', 'hasAttribute', 'getAttribute',
                'getAttributes', 'setAttribute', 'removeAttribute'
            )
            );
        $fieldView1->expects($this->atLeastOnce())
            ->method('hasAttribute')
            ->with('form')
            ->will($this->returnValue(true));
        $fieldView2 = $this->getMock(
            'FSi\Component\DataSource\Field\FieldViewInterface',
            array(
                '__construct', 'getName', 'getType', 'getComparison', 'getParameter',
                'getDataSourceView', 'setDataSourceView', 'hasAttribute', 'getAttribute',
                'getAttributes', 'setAttribute', 'removeAttribute'
            )
        );
        $fieldView2->expects($this->atLeastOnce())
            ->method('hasAttribute')
            ->with('form')
            ->will($this->returnValue(false));
        $fieldView3 = $this->getMock(
            'FSi\Component\DataSource\Field\FieldViewInterface',
            array(
                '__construct', 'getName', 'getType', 'getComparison', 'getParameter',
                'getDataSourceView', 'setDataSourceView', 'hasAttribute', 'getAttribute',
                'getAttributes', 'setAttribute', 'removeAttribute'
            )
        );
        $fieldView3->expects($this->atLeastOnce())
            ->method('hasAttribute')
            ->with('form')
            ->will($this->returnValue(true));
        $datasourceView->expects($this->atLeastOnce())
            ->method('getFields')
            ->will($this->returnValue(array($fieldView1, $fieldView2, $fieldView3)));

        $this->assertEquals(
            $this->extension->datasourceFilterCount($datasourceView),
            2
        );
    }

    public function testDataSourceRenderBlock()
    {
        $this->twig->addExtension($this->extension);
        // force initRuntime()
        $this->twig->loadTemplate('datasource.html.twig');
        $template = $this->getTemplateMock();

        $template->expects($this->at(0))
            ->method('hasBlock')
            ->with('datasource_datasource_filter')
            ->will($this->returnValue(false));

        $template->expects($this->at(1))
            ->method('getParent')
            ->with(array())
            ->will($this->returnValue(false));

        $template->expects($this->at(2))
            ->method('hasBlock')
            ->with('datasource_filter')
            ->will($this->returnValue(true));

        $datasourceView = $this->getDataSourceView('datasource');
        $this->extension->setTheme($datasourceView, $template);

        $template->expects($this->at(3))
            ->method('displayBlock')
            ->with('datasource_filter', array(
                'datasource' => $datasourceView,
                'vars' => array(),
                'global_var' => 'global_value'
            ))
            ->will($this->returnValue(true));

        $this->extension->datasourceFilter($datasourceView);
    }

    public function testDataSourceRenderBlockFromParent()
    {
        $this->twig->addExtension($this->extension);
        // force initRuntime()
        $this->twig->loadTemplate('datasource.html.twig');

        $parent = $this->getTemplateMock();
        $template = $this->getTemplateMock();

        $template->expects($this->at(0))
            ->method('hasBlock')
            ->with('datasource_datasource_filter')
            ->will($this->returnValue(false));

        $template->expects($this->at(1))
            ->method('getParent')
            ->with(array())
            ->will($this->returnValue(false));

        $template->expects($this->at(2))
            ->method('hasBlock')
            ->with('datasource_filter')
            ->will($this->returnValue(false));

        $template->expects($this->at(3))
            ->method('getParent')
            ->with(array())
            ->will($this->returnValue($parent));

        $parent->expects($this->at(0))
            ->method('hasBlock')
            ->with('datasource_filter')
            ->will($this->returnValue(true));

        $datasourceView = $this->getDataSourceView('datasource');
        $this->extension->setTheme($datasourceView, $template);

        $parent->expects($this->at(1))
            ->method('displayBlock')
            ->with('datasource_filter', array(
                'datasource' => $datasourceView,
                'vars' => array(),
                'global_var' => 'global_value'
            ))
            ->will($this->returnValue(true));

        $this->extension->datasourceFilter($datasourceView);
    }

    private function getRouter()
    {
        $router = $this->getMock(
            '\Symfony\Component\Routing\RouterInterface',
            array('getRouteCollection', 'match', 'setContext', 'getContext', 'generate')
        );
        $router->expects($this->any())
            ->method('generate')
            ->will($this->returnValue('some_route'));

        return $router;
    }

    private function getContainer()
    {
        $container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
        $container->expects($this->any())
            ->method('get')
            ->with('router')
            ->will($this->returnValue($this->getRouter()));

        return $container;
    }

    private function getDataSourceView($name)
    {
        $datasourceView = $this->getMockBuilder('FSi\Component\DataSource\DataSourceViewInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $datasourceView->expects($this->any())
            ->method('getName')
            ->will($this->returnValue($name));

        return $datasourceView;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getTemplateMock()
    {
        return $this->getMock(
            '\Twig_Template',
            array(
                'hasBlock', 'render', 'display', 'getEnvironment', 'displayBlock',
                'getParent', 'getTemplateName', 'doDisplay', 'getDebugInfo'
            ),
            array($this->twig)
        );
    }

    /**
     * @return FormExtension
     */
    private function getFormExtension($legacy)
    {
        if ($legacy) {
            $rendererEngine = new TwigRendererEngine(array('form_div_layout.html.twig',));
            $renderer = new TwigRenderer($rendererEngine);
            $formExtension = new FormExtension($renderer);
        } else {
            $formExtension = new FormExtension();
        }

        return $formExtension;
    }
}
