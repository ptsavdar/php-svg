<?php

namespace JangoBrick\SVG\Nodes;

use JangoBrick\SVG\Nodes\Structures\SVGStyle;
use JangoBrick\SVG\Rasterization\SVGRasterizer;
use JangoBrick\SVG\Utilities\SVGStyleParser;

/**
 * Represents an SVG image element that contains child elements.
 */
abstract class SVGNodeContainer extends SVGNode
{
    /** @var SVGNode[] $children This node's child nodes. */
    protected $children;

    /**
     * @var [] $globalStyles for this node and its child nodes
     * it's a 2D array containing the selector as key and an array of styles as value.
     */
    protected $containerStyles;

    public function __construct()
    {
        parent::__construct();

        $this->containerStyles = array();
        $this->children = array();
    }

    /**
     * Adds an SVGNode instance to the end of this container's child list.
     * Does nothing if it already exists.
     *
     * @param SVGNode $node The node to add to this container's children.
     *
     * @return $this This node instance, for call chaining.
     */
    public function addChild(SVGNode $node)
    {
        if ($node === $this || $node->parent === $this) {
            return $this;
        }

        if (isset($node->parent)) {
            $node->parent->removeChild($node);
        }

        $this->children[] = $node;
        $node->parent     = $this;

        if ($node instanceof SVGStyle) {
            // if node is SVGStyle then add rules to container's style
            $this->addContainerStyle($node);
        }

        return $this;
    }

    /**
     * Removes a child node, given either as its instance or as the index it's
     * located at, from this container.
     *
     * @param SVGNode|int $nodeOrIndex The node (or respective index) to remove.
     *
     * @return $this This node instance, for call chaining.
     */
    public function removeChild($nodeOrIndex)
    {
        $index = $this->resolveChildIndex($nodeOrIndex);
        if ($index === false) {
            return $this;
        }

        $node         = $this->children[$index];
        $node->parent = null;

        array_splice($this->children, $index, 1);

        return $this;
    }

    /**
     * Resolves a child node to its index. If an index is given, it is returned
     * without modification.
     *
     * @param SVGNode|int $nodeOrIndex The node (or respective index).
     *
     * @return int|false The index, or false if argument invalid or not a child.
     */
    private function resolveChildIndex($nodeOrIndex)
    {
        if (is_int($nodeOrIndex)) {
            return $nodeOrIndex;
        } elseif ($nodeOrIndex instanceof SVGNode) {
            return array_search($nodeOrIndex, $this->children, true);
        }

        return false;
    }

    /**
     * @return int The amount of children in this container.
     */
    public function countChildren()
    {
        return count($this->children);
    }

    /**
     * @return SVGNode The child node at the given index.
     */
    public function getChild($index)
    {
        return $this->children[$index];
    }

    /**
     * Adds the SVGStyle element rules to container's styles.
     *
     * @param SVGStyle $styleNode The style node to add rules from.
     *
     * @return $this This node instance, for call chaining.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function addContainerStyle(SVGStyle $styleNode)
    {
        $newStyles = SVGStyleParser::parseCss($styleNode->getCss());
        $this->containerStyles = array_merge($this->containerStyles, $newStyles);

        return $this;
    }


    public function rasterize(SVGRasterizer $rasterizer)
    {
        if ($this->getComputedStyle('display') === 'none') {
            return;
        }

        // 'visibility' can be overridden -> only applied in shape nodes.

        foreach ($this->children as $child) {
            $child->rasterize($rasterizer);
        }
    }

    /**
     * Returns a node's 'global' style rules.
     *
     * @param SVGNode $node The node for which we need to obtain.
     * its container style rules.
     *
     * @return array The style rules to be applied.
     */
    public function getContainerStyleForNode(SVGNode $node)
    {
        $pattern = $node->getIdAndClassPattern();

        return $this->getContainerStyleByPattern($pattern);
    }

    /**
     * Returns a style rules provided a given a node's id and class pattern.
     *
     * @param $pattern The node's id and class pattern for which we need to obtain
     * its container style rules.
     *
     * @return array The style rules to be applied.
     */
    public function getContainerStyleByPattern($pattern)
    {
        if ($pattern === null) {
            return array();
        }
        $nodeStyles = array();
        if (!empty($this->parent)) {
            $nodeStyles = $this->parent->getContainerStyleByPattern($pattern);
        }
        $keys = $this->pregGrepStyle($pattern);
        foreach ($keys as $key) {
            $nodeStyles = array_merge($nodeStyles, $this->containerStyles[$key]);
        }

        return $nodeStyles;
    }

    /**
     * Returns the array consisting of the keys of the style rules that match the given pattern.
     *
     * @param $pattern The pattern to search for, as a string
     *
     * @return array The matches array
     */
    private function pregGrepStyle($pattern)
    {
        return preg_grep($pattern, array_keys($this->containerStyles));
    }
}
