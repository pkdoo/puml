<?php
/**
 * Puml
 *
 * PHP Version 5.3
 *
 * @category  Puml
 * @package   Puml\Transformer
 * @author    Danny van der Sluijs <danny.vandersluijs@fleppuhstein.com>
 * @copyright 2012 Danny van der Sluijs <www.fleppuhstein.com>
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 */
namespace Puml\Transformer;

use phpDocumentor\GraphViz\Graph;
use phpDocumentor\GraphViz\Node;
use phpDocumentor\GraphViz\Edge;

/**
 * A transformer which uses a intermediate dot file to tranform the object into
 * an image.
 */
class GraphViz extends Base implements Transformer
{
    const NEWLINE = "\l";

    /**
     * The graphviz graph
     * @var \phpDocumentor\GraphViz\Graph
     */
    protected $graph;

    /**
     * The level in parent(s)
     * @var integer
     */
    protected $level = 0;

    /**
     * Get the transformation possiblities supported by this transformer
     *
     * @return array<string>
     * @since 0.1
     */
    public static function getTransformationPossibilities()
    {
        return array(
            'png',
            'pdf',
            'dot'
        );
    }

    /**
     * Pre execute the transformation. Supports fluent interface
     *
     * @return \Puml\Transformer\Base
     * @since 0.1
     */
    public function preExecute()
    {
        $this->graph = new Graph();
        return $this;
    }

    /**
     * Execute the transformation
     *
     * @return void
     * @since 0.1
     */
    public function execute()
    {
        $this->transformObject($this->getObject());
        if ($this->getObject()->hasParent()) {
            $this->transformParent($this->getObject());
        }

        return $this;
    }

    /**
     * Post execute the transformation. Supports fluent interface
     *
     * @return \Puml\Transformer\Base
     * @since 0.1
     */
    public function postExecute()
    {
        $type = escapeshellarg($this->getTransformation());
        $filename = escapeshellarg($this->getFilename());

        // write the dot file to a temporary file
        $tmpfile = tempnam(sys_get_temp_dir(), 'gvz');
        file_put_contents($tmpfile, (string) $this->graph);

        // escape the temp file for use as argument
        $tmpfile_arg = escapeshellarg($tmpfile);

        // create the dot output
        $output = array();
        $code = 0;
        exec("dot -T$type -o$filename -Kfdp -n < $tmpfile_arg 2>&1", $output, $code);
        unlink($tmpfile);

        if ($code != 0) {
            throw new \Exception(
                'An error occurred while creating the graph; GraphViz returned: '
                . implode(PHP_EOL, $output)
            );
        }
        return $this;
    }

    /**
     * Transform the parent
     *
     * @param \Puml\Model\Object $child  The direct child of the parent
     *
     * @return void
     * @since 0.1
     * @throws Exception
     */
    protected function transformParent(\Puml\Model\Object $child)
    {
        if (!$child->hasParent()) {
            throw new \Exception('Unable to transform parent, when no parent available, on class ' . $child->getName());
        }

        $this->level++;

        $parent = $child->getParent();
        $this->transformObject($parent);

        $edge = new Edge(
            $this->graph->findNode($child->getName()),
            $this->graph->findNode($parent->getName())
        );
        $edge->setArrowhead('empty');
        $this->graph->link($edge);

        if ($parent->hasParent()) {
            $this->transformParent($parent);
        }
    }

    /**
     * Transform the object to an UML scheme
     *
     * @param \Puml\Model\Object $object
     *
     * @return void
     * @since 0.1
     */
    protected function transformObject(\Puml\Model\Object $object)
    {
        $label = implode(
            '|',
            array(
                addslashes($object->getName()),
                implode($this->transformProperties($object->getProperties())),
                implode($this->transformMethods($object->getMethods()))
            )
        );

        $node = new Node($object->getName());
        $node
            ->setShape('record')
            ->setPos('0, ' . (0 + ($this->level * 3)) . '!')
            ->setLabel('"{' . $label . '}"');

        $this->graph->setNode($node);
    }

    /**
     * Transform the properties to string representations
     *
     * @param array<\Puml\Model\Property> $properties
     *
     * @return array<string>
     * @since 0.1
     */
    protected function transformProperties($properties)
    {
        $transformeredProperties =array();

        foreach ($properties as $property) {
            $transformeredProperties[] =
                $this->decorateVisibility($property->getVisibility()) .
                $property->getName() . ' : ' .
                addslashes($property->getType()) . self::NEWLINE;
        }

        return $transformeredProperties;
    }

    /**
     * Transform the methods
     *
     * @param array<\Puml\Model\Method> $methods
     *
     * @return array<string>
     * @since 0.1
     */
    protected function transformMethods($methods)
    {
        $transformeredMethods = array();

        foreach ($methods as $method) {
            $transformeredMethods[] =
                $this->decorateVisibility($method->getVisibility()) .
                $method->getName() .
                '(' . $this->transformParameters($method->getParameters()) . ') : ' .
                addslashes($method->getType()) . self::NEWLINE;
        }

        return $transformeredMethods;
    }

    /**
     * Transform the parameters
     *
     * @param array<\Puml\Model\Parameter>
     *
     * @return string
     * @since 0.1
     */
    protected function transformParameters($parameters)
    {
        $transformedParameters = '';

        foreach ($parameters as $parameter) {
            $transformedParameters .=
            $parameter->getName() .
            ' : ' .
            addslashes($parameter->getType()) . ' ';
        }

        return substr($transformedParameters, 0, -1);
    }
}
