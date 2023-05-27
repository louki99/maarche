<?php
/*
 * Copyright (C) 2015 Maarch
 *
 * This file is part of dependency xml.
 *
 * Dependency xml is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Dependency xml is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with dependency xml.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Process DOM XML Template documents
 *
 * @author Cyril Vazquez <cyril.vazquez@maarch.org>
 */
class DOMTemplateProcessor extends \DOMXPath
{
    /**
     * The array of parsed processing instructions
     * @var array
     */
    protected $parsedPis = array();

    /**
     * The array of parsed text instructions
     * @var array
     */
    protected $parsedTexts = array();

    /**
     * The storage of already merged nodes
     * @var array
     */
    protected $mergedNodes;

    /**
     * The storage of already merged form nodes
     * @var array
     */
    protected $mergedForms;

    /**
     * The data sources
     * @var array
     */
    protected $sources = array();

    /**
     * The variables
     * @var array
     */
    protected $variables = array();

    /**
     * Constructor
     * @param DOMDocument $document
     */
    public function __construct($document)
    {
        parent::__construct($document);

        $this->xinclude();
        $this->xinclude();
        $this->xinclude();
        $this->xinclude();
        $this->mergedNodes = new \SplObjectStorage();
        $this->mergedForms = new \SplObjectStorage();

        $this->bindVariable("_SESSION", $_SESSION);
        $this->bindVariable("_SERVER", $_SERVER);
        $this->bindVariable("_GET", $_GET);
        $this->bindVariable("_POST", $_POST);
        $this->bindVariable("_ENV", $_ENV);
        $this->bindVariable("_FILES", $_FILES);
    }

    /**
     * Process xinclude processing instructions
     * @param DOMNode $node The context node. If omitted the method processes the entire document tree.
     */
    protected function xinclude($node = null)
    {
        if ($pis = $this->query("descendant-or-self::processing-instruction('xinclude')", $node)) {
            foreach ($pis as $pi) {
                $includeFragment = $this->document->createDocumentFragment();
                $source = file_get_contents(__DIR__ . trim($pi->data));
                if (!$source) {
                    throw new \Exception("Error including Xml fragment: fragment '$pi->data' could not be parsed");
                }

                $includeFragment->appendXML($source);

                $pi->parentNode->replaceChild($includeFragment, $pi);
            }
        }
    }

    /**
     * Bind a variable
     * @param string $name The name of the variable
     * @param string &$variable The reference of the value
     */
    public function bindVariable($name, &$variable)
    {
        $this->variables[$name] = &$variable;
    }

    /**
     * Set a source for merge
     * @param string $name The name of the data source
     * @param string $value The value
     */
    public function setSource($name, $value)
    {
        $this->sources[$name] = $value;
    }

    /**
     * Remove empty elements and attributes
     * @param DOMNode $node The context node. If omitted the entire document will be processed.
     */
    public function removeEmptyNodes($node=null)
    {
        if (!$node) {
            $node = $this->document->documentElement;
        }

        switch ($node->nodeType) {
            case \XML_ELEMENT_NODE:
                $childNodeList = $node->childNodes;
                for ($i=$childNodeList->length-1; $i>=0; $i--) {
                    $this->removeEmptyNodes($childNodeList->item($i));
                }

                $childNodeList = $node->childNodes;
                if ($childNodeList->length == 0 && !$node->hasAttributes()) {
                    $node->parentNode->removeChild($node);
                }
                break;

            case \XML_ATTRIBUTE_NODE:
                if (empty($node->value)) {
                    $node->parentNode->removeChild($node);
                }
                break;

            case \XML_TEXT_NODE:
                if (ctype_space($node->nodeValue)) { //&& $node->previousSibling && $node->previousSibling->nodeType == \XML_TEXT_NODE) {
                    $node->nodeValue = trim($node->nodeValue);
                }
                break;
        }
    }

    /* -------------------------------------------------------------------------
    - MERGE processing instructions
    ------------------------------------------------------------------------- */
    /**
     * Merges the processing instructions on the given node and its chil nodes.
     *
     * @param string $node The context node. If omitted the entire document will be processed.
     * @param string $source The data source. If omitted, all merge instruction path must be existing sources
     */
    public function merge($node = null, $source = null)
    {
        // Avoid garbage nodes merge
        if (!isset($this->mergedNodes)) {
            $this->mergedNodes = new \SplObjectStorage();
        }

        if (!isset($this->mergedForms)) {
            $this->mergedForms = new \SplObjectStorage();
        }

        if ($node && $this->mergedNodes->contains($node)) {
            return;
        }

        $mergeNodes = $this->query("descendant-or-self::processing-instruction('merge') | descendant-or-self::text()[contains(., '[?merge')] | descendant-or-self::*/@*[contains(., '[?merge')]", $node);

        $this->mergedObjects = array();

        foreach ($mergeNodes as $i => $mergeNode) {
            switch ($mergeNode->nodeType) {
                case XML_PI_NODE:
                    if (!isset($this->parsedPis[$mergeNode->data])) {
                        $this->parsedPis[$mergeNode->data] = $this->parse($mergeNode->data);
                    }
                    $instr = $this->parsedPis[$mergeNode->data];

                    if ($merged = $this->mergePi($mergeNode, $instr, $source)) {
                        if ($mergeNode->parentNode) {
                            $mergeNode->parentNode->removeChild($mergeNode);
                        }
                    }
                    break;

                case XML_TEXT_NODE:
                case XML_ELEMENT_NODE:
                case XML_ATTRIBUTE_NODE:
                default:
                    $this->mergeTextNode($mergeNode, $source);
            }
        }

        /*$this->mergePis($node, $source);

        $this->mergeTextNodes($node, $source);*/

        $this->mergeForms();

        if ($node) {
            $this->mergedNodes->attach($node);
        }
    }

    protected function mergePi($pi, $instr, $source = null)
    {
        // Get value by reference
        $value = &$this->getData($instr, $source);

        // Use value with selected target
        if (isset($instr->params['var'])) {
            $this->addVar($instr->params['var'], $value);

            $pi->parentNode->removeChild($pi);

            return false;
        }

        // Get type of value
        $type = gettype($value);

        switch (true) {
            // If value is scalar, merge text before Pi
            case $type == 'string':
            case $type == 'integer':
            case $type == 'double':
                return $this->mergeText($pi, $instr, $value);

            // Value is bool, remove target sibling if false
            case $type == 'boolean':
                return $this->mergeBool($pi, $instr, $value);

            // Value is null, no action
            case $type == 'NULL':
                return true;

            // Value is array, merge target by iterating over array
            case $type == 'array':
                return $this->mergeArray($pi, $instr, $value);

            case $type == 'object':
                switch (true) {
                    // Object merged with a form
                    case ($targetForm = $this->query("following-sibling::form", $pi)->item(0)):
                        $this->mergedForms->attach($targetForm, array($pi, $instr, $value));
                        break;

                    // ArrayObject -> merge array
                    case ($value instanceof \ArrayAccess && $value instanceof \Iterator):
                        return $this->mergeArray($pi, $instr, $value);

                    // DOMNode -> merge as xml
                    case $value instanceof \DOMNode:
                        return $this->mergeNode($pi, $instr, $value);

                    // If value is an object but no form : merge string version if possible
                    case method_exists($value, '__toString'):
                        return $this->mergeText($pi, $instr, (string)$value);
                }

        }
    }

    protected function addVar($name, &$var)
    {
        $this->variables[$name] = $var;
    }

    protected function mergeForms()
    {
        $this->mergedForms->rewind();
        while ($this->mergedForms->valid()) {
            $index  = $this->mergedForms->key();
            $targetForm = $this->mergedForms->current();
            list($pi, $instr, $object) = $this->mergedForms->getInfo();

            $params = $instr->params;

            if (isset($params['source'])) {
                $this->setSource($params['source'], $object);
            }

            $this->mergeObjectProperties($targetForm, $object, $params, $oname = false);

            //$pi->parentNode->removeChild($pi);

            $this->mergedForms->next();
        }
    }

    protected function mergeTextNodes($node = null, $source = null)
    {
        $textNodes = $this->query("descendant-or-self::text()[contains(., '[?merge')] | descendant-or-self::*/@*[contains(., '[?merge')]", $node);

        for ($i = 0, $l = $textNodes->length; $i < $l; $i++) {
            $textNode = $textNodes->item($i);
            $this->mergeTextNode($textNode, $source);
        }
    }

    protected function mergeTextNode($textNode, $source = null)
    {
        //$nodeXml = $this->saveXml($textNode);
        $nodeValue = $textNode->nodeValue;
        if (isset($this->parsedTexts[$nodeValue])) {
            $instructions = $this->parsedTexts[$nodeValue];
        } else {
            preg_match_all("#(?<pi>\[\?merge (?<instr>(?:(?!\?\]).)*)\?\])#", $nodeValue, $pis, PREG_SET_ORDER);
            $instructions = array();
            foreach ($pis as $i => $pi) {
                $instructions[$pi['pi']] = $this->parse($pi['instr']);
            }
            $this->parsedTexts[$nodeValue] = $instructions;
        }

        foreach ($instructions as $pi => $instr) {
            $value = $this->getData($instr, $source);
            if (is_scalar($value) || is_null($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $mergedValue = str_replace($pi, (string)$value, $textNode->nodeValue);
                $mergedValue = htmlentities($mergedValue);
                $textNode->nodeValue = str_replace($pi, $value, $mergedValue);
            }
        }

        // If text is in an attribute that is empty, remove attribute
        if ($textNode->nodeType == XML_ATTRIBUTE_NODE && empty($textNode->value)) {
            $textNode->parentNode->removeAttribute($textNode->name);
        }
    }

    protected function mergeText($pi, $instr, $value)
    {
        $params = $instr->params;
        switch (true) {
            case isset($params['attr']):
                if (!$targetNode = $this->query("following-sibling::*", $pi)->item(0)) {
                    return true;
                }
                $targetNode->setAttribute($params['attr'], $value);
                break;

            case isset($params['render']):
                if (!$params['render']) {
                    $fragment = $value;
                } else {
                    $fragment = $params['render'];
                }
                if (!isset($this->fragments[$fragment])) {
                    return true;
                }
                $targetNode = $this->fragments[$fragment]->cloneNode(true);
                $this->merge($targetNode);
                $pi->parentNode->insertBefore($targetNode, $pi);
                break;

            default:
                $targetNode = $this->document->createTextNode($value);
                $pi->parentNode->insertBefore($targetNode, $pi);
        }

        return true;
    }

    protected function mergeArray($pi, $instr, &$array)
    {
        $params = $instr->params;

        if (isset($params['include'])) {
            $filename = $params['include'];
            $source = file_get_contents(__DIR__ . "/" . $filename);

            $targetNode = $this->document->createDocumentFragment();
            $targetNode->appendXML($source);
        } elseif (!$targetNode = $this->query("following-sibling::*", $pi)->item(0)) {
            return true;
        }

        reset($array);
        if ($count = count($array)) {
            $i = 0;
            while ($i < $count) {
                //do {
                $itemNode = $targetNode->cloneNode(true);
                $itemData = current($array);
                if (isset($params['source'])) {
                    $this->setSource($params['source'], $itemData);
                }

                $this->merge($itemNode, $itemData);
                $pi->parentNode->insertBefore($itemNode, $pi);

                @next($array);
                $i++;
                /*} while (
                    @next($array) !== false
                );*/
            }
        }
        // Remove targetNode (row template)
        if ($targetNode->parentNode) {
            $targetNode = $targetNode->parentNode->removeChild($targetNode);
        }

        // Add to mergedNodes to prevent other calls to merge
        $this->mergedNodes->attach($targetNode);

        return true;
    }

    protected function mergeObject($pi, $instr, $object)
    {
        $params = $instr->params;
        if (!$targetNode = $this->query("following-sibling::*", $pi)->item(0)) {
            return true;
        }

        if (isset($params['source'])) {
            $this->setSource($params['source'], $object);
        }

        $this->mergeObjectProperties($targetNode, $object, $params, $oname = false);

        return true;
    }

    protected function mergeObjectProperties($targetNode, $object, $params, $oname = false)
    {
        foreach ($object as $pname => $pvalue) {
            if ($oname) {
                $pname = $oname . "." . $pname;
            }

            if (is_scalar($pvalue) || (is_object($pvalue) && method_exists($pvalue, '__toString'))) {
                $this->mergeObjectProperty($targetNode, $pvalue, $params, $pname);
            }
            /*elseif (is_object($pvalue))
                $this->mergeObjectProperties($targetNode, $pvalue, $params, $pname);
            elseif (is_array($pvalue))
                foreach ($pvalue as $key => $item)
                    $this->mergeObjectProperties($targetNode, $pvalue, $params, $pname . "[$key]");*/
        }
    }

    protected function mergeObjectProperty($targetNode, $value, $params, $name)
    {
        $elements = $this->query("descendant-or-self::*[@name='$name']", $targetNode);
        for ($i = 0, $l = $elements->length; $i < $l; $i++) {
            $element = $elements->item($i);
            switch (strtolower($element->nodeName)) {
                // Form Input
                case 'input':
                    switch ($element->getAttribute('type')) {
                        case 'checkbox':
                            if (is_bool($value)) {
                                if ($value) {
                                    $element->setAttribute('checked', 'true');
                                } else {
                                    $element->removeAttribute('checked');
                                }
                            } else {
                                if ($element->getAttribute('value') == $value) {
                                    $element->setAttribute('checked', 'true');
                                } else {
                                    $element->removeAttribute('checked');
                                }
                            }

                            break;

                        case 'radio':
                            if ($element->getAttribute('value') == $value) {
                                $element->setAttribute('checked', 'true');
                            } else {
                                $element->removeAttribute('checked');
                            }
                            break;

                        default:
                            $element->setAttribute('value', $value);
                    }
                    break;

                // Select
                case 'select':
                    $value = $this->quote($value);
                    if ($option = $this->query(".//option[@value=" . $value . "]", $element)->item(0)) {
                        $option->setAttribute('selected', 'true');
                        if ($optGroup = $this->query("parent::optgroup", $option)->item(0)) {
                            $optGroup->removeAttribute('disabled');
                        }
                    }
                    break;

                // Textareas
                case 'textarea':
                    $element->nodeValue = $value;
                    break;
            }
        }
    }

    /**
     * Merge a boolean
     * @param DOMNode $pi
     * @param string $instr
     * @param boolean $bool
     *
     * @return bool
     */
    protected function mergeBool($pi, $instr, $bool)
    {
        $params = $instr->params;
        if (isset($params['include'])) {
            $res = $params['include'];
            $targetNode = $this->document->createDocumentFragment();
        } elseif (!$targetNode = $this->query("following-sibling::*", $pi)->item(0)) {
            return true;
        }

        if (isset($params['attr'])) {
            if ($bool == false) {
                $targetNode->removeAttribute($params['attr']);
            } else {
                $targetNode->setAttribute($params['attr'], $params['attr']);
            }
        } else {
            if (isset($params['source'])) {
                $this->setSource($params['source'], $bool);
            }
            if ($bool == false) {
                if ($targetNode->parentNode) {
                    $parentNode = $targetNode->parentNode;
                    $targetNode = $parentNode->removeChild($targetNode);
                }

                // Add to mergedNodes to prevent other calls to merge
                $this->mergedNodes->attach($targetNode);
            }
        }

        return true;
    }

    /**
     * Merge a node
     * @param DOMNode $pi
     * @param string $instr
     * @param DOMNode $DOMNode
     *
     * @return bool
     */
    public function mergeNode($pi, $instr, $DOMNode)
    {
        $pi->parentNode->insertBefore($DOMNode, $pi);

        return true;
    }

    /* ------------------------------------------------------------------------
        Data sources management
    ------------------------------------------------------------------------ */
    protected function &getData($instr, $source = null)
    {
        $value = null;

        $steps = $instr->source;

        // First step defines source
        $type = $steps[0][0];
        switch ($type) {
            case 'arg':
                $value = &$source;
                break;
            case 'source':
                $name = $steps[0][1];
                if (isset($this->sources[$name])) {
                    $value = &$this->sources[$name];
                } elseif (is_scalar($name)) {
                    if ($name[0] == '"' || $name[0] == "'") {
                        $value = substr($name, 1, -1);
                    } elseif (is_numeric($name)) {
                        $value = $name;
                    }
                }
                break;
            case 'var':
                $name = $steps[0][1];
                if (isset($this->variables[$name])) {
                    $value = &$this->variables[$name];
                }
                break;
            case 'method':
                $route = $steps[0][1];
                $methodRouter = new \core\Route\MethodRouter($route);
                $serviceObject = $methodRouter->service->newInstance();
                break;
        }

        for ($i = 1, $l = count($steps); $i < $l; $i++) {
            $value = &$this->stepData($steps[$i], $value);
        }

        return $value;
    }

    protected function &stepData($step, $source)
    {
        $value = null;
        switch ($step[0]) {
            case 'func':
                $value = &$this->stepFunc($step[1], $step[2], $source);
                break;

            case 'offset':
                $key = &$this->getParamValue($step[1], $source);
                if (is_array($source) && isset($source[$key])) {
                    $value = &$source[$key];
                }
                break;

            case 'prop':
                if (isset($source->{$step[1]})) {
                    $value = &$source->{$step[1]};
                }
                break;
        }

        return $value;
    }

    protected function &stepFunc($name, $params = array(), $source = null)
    {
        $value = null;
        foreach ($params as $i => $param) {
            $params[$i] = &$this->getParamValue($param, $source);
        }

        if (is_object($source) && method_exists($source, $name)) {
            $value = call_user_func_array(array($source, $name), $params);

            return $value;
        }

        switch ($name) {
            // Callback functions
            case 'func':
                $func = $params[0];
                if (!isset($this->functions[$func])) {
                    break;
                }
                $callback = $this->functions[$func];
                array_unshift($params, $source);
                array_unshift($params, $this);
                $value = @call_user_func_array($callback, $params);
                break;

            // Array functions
            case 'length':
            case 'count':
                $value = @count($source);
                break;
            case 'key':
                $value = @key($source);
                break;
            case 'current':
                $value = @current($source);
                break;
            case 'first':
                $value = @reset($source);
                break;
            case 'next':
                $value = @next($source);
                break;
            case 'prev':
                $value = @prev($source);
                break;
            case 'end':
                $value = @end($source);
                break;
            case 'pos':
                $pos = null;
                foreach ((array)$source as $key => $value) {
                    $pos++;
                    if ($key == @key($source)) {
                        break;
                    }
                }
                if (!is_null($pos)) {
                    $value = $pos;
                }
                break;
            case 'islast':
                $value = ((@key($source) + 1) == @count($source));
                break;
            case 'slice':
                $value = @array_slice($source, $params[0], $params[1]);
                break;
            case 'arraykeyexists':
                $value = @array_key_exists($params[0], $source);
                break;
            case 'inarray':
                $value = @in_array($params[0], $source);
                break;

            // Variable functions
            case 'type':
                $value = @gettype($source);
                break;
            case 'not':
                $value = @!$source;
                break;
            case 'empty':
                $value = @empty($source);
                break;
            case 'isset':
                $value = @isset($source);
                break;
            case 'isarray':
                $value = @is_array($source);
                break;
            case 'isbool':
                $value = @is_bool($source);
                break;
            case 'isfloat':
            case 'isdouble':
            case 'isreal':
                $value = @is_float($source);
                break;
            case 'isint':
            case 'isinteger':
            case 'islong':
                $value = @is_int($source);
                break;
            case 'isnull':
                $value = @is_null($source);
                break;
            case 'isnotnull':
                $value = @!is_null($source);
                break;
            case 'isnumeric':
                $value = @is_numeric($source);
                break;
            case 'isobject':
                $value = @is_object($source);
                break;
            case 'isscalar':
                $value = @is_scalar($source);
                break;
            case 'isstring':
                $value = @is_string($source);
                break;
            case 'int':
                $value = @intval($source);
                break;
            case 'float':
                $value = @floatval($source);
                break;
            case 'string':
                $value = @strval($source);
                break;
            case 'bool':
                $value = @(bool)$source;
                break;
            case 'array':
                if (!is_array($source)) {
                    if (is_null($source)) {
                        $value = [];
                    } else {
                        $value = [$source];
                    }
                } else {
                    $value = $source;
                }
                break;
            case 'attr':
                if (isset($source->{$params[0]})) {
                    $value = @$source->{$params[0]};
                }
                break;
            case 'in':
                $value = false;
                $i = 0;
                while (isset($params[$i])) {
                    $value = $value || $source == $params[$i];
                    $i++;
                }
                break;
            case 'between':
                if (isset($params[2]) && $params[2]) {
                    $value = ($source > $params[0] && $source < $params[1]);
                } else {
                    $value = ($source >= $params[0] && $source <= $params[1]);
                }
                break;
            case 'ifeq':
                $value = ($source == $params[0]);
                break;
            case 'ifne':
                $value = ($source != $params[0]);
                break;
            case 'ifgt':
                $value = ($source > $params[0]);
                break;
            case 'ifgte':
                $value = ($source >= $params[0]);
                break;
            case 'iflt':
                $value = ($source < $params[0]);
                break;
            case 'iflte':
                $value = ($source <= $params[0]);
                break;
            case 'contains':
                $value = (strpos($source, $params[0]) !== false);
                break;
            case 'starts-with':
                $value = (strpos($source, $params[0]) === 0);
                break;
            case 'ends-with':
                $value = (strrpos($source, $params[0]) === (strlen($source) - strlen($params[0]) + 1));
                break;
            case 'bit':
                $value = ($source & (int)$params[0]) > 0;
                break;

            case 'then':
                if ($source) {
                    $value = $params[0];
                } elseif (isset($params[1])) {
                    $value = $params[1];
                }
                break;

            case 'coalesce':
                if (is_null($source)) {
                    $value = $params[0];
                } else {
                    $value = $source;
                }
                break;

            // String functions
            case 'format':
            case 'fmt':
                $value = @sprintf($params[0], $source);
                break;
            case 'match':
                $value = (bool)@preg_match($params[0], $source);
                break;
            case 'upper':
                $value = @strtoupper($source);
                break;
            case 'lower':
                $value = @strtolower($source);
                break;
            case 'ucfirst':
                $value = @ucfirst($source);
                break;
            case 'lcfirst':
                $value = @lcfirst($source);
                break;
            case 'ucwords':
                $value = @ucwords($source);
                break;
            case 'split':
            case 'explode':
                $value = @explode($params[0], $source);
                break;
            case 'substr':
                if (isset($params[1])) {
                    $value = @substr($source, $params[0], $params[1]);
                } else {
                    $value = @substr($source, $params[0]);
                }
                break;
            case 'join':
            case 'implode':
                $value = @implode($params[0], $source);
                break;
            case 'constant':
                $value = @constant($source);
                if (is_null($value) && $params[0]) {
                    $value = $source;
                }
                break;
            case 'print':
                $value = @print_r($source, true);
                break;
            case 'json':
                if (isset($params[0])) {
                    $options = \JSON_PRETTY_PRINT;
                } else {
                    $options = 0;
                }
                $value = @json_encode($source, $options);
                break;
            case 'parse':
                if (isset($params[0])) {
                    $value = @json_decode($source, $params[0]);
                } else {
                    $value = @json_decode($source);
                }
                break;
            case 'encodehtml':
                $value = @htmlentities($source);
                break;
            case 'decodehtml':
                $value = @html_entity_decode($source);
                break;
            case 'base64':
                $value = @base64_encode($source);
                break;
            case 'cat':
                $value = @implode($params);
                break;
            case 'dump':
                ob_start();
                var_dump($source);
                $value = @ob_get_clean();
                break;
            case 'translate':
                if (is_string($source) && $this->translator) {
                    $catalog = null;
                    if (isset($params[0])) {
                        $catalog = $params[0];
                    }
                    $context = null;
                    if (isset($params[1])) {
                        $context = $params[1];
                    }
                    $value = $this->translator->getText($source, $context, $catalog);
                } else {
                    $value = $source;
                }
                break;

            // Number functions
            case 'add':
                $value = @($source + $params[0]);
                break;
            case 'mul':
                $value = @($source * $params[0]);
                break;
            case 'div':
                $value = @($source / $params[0]);
                break;
            case 'mod':
                $value = @($source % $params[0]);
                break;
        }

        return $value;
    }

    protected function &getParamValue($param, $source = null)
    {
        if ($param[0] == "'" || $param[0] == '"') {
            $value = substr($param, 1, -1);
        } elseif (is_numeric($param)) {
            $value = $param;
        } else {
            $instr = $this->parse($param);
            $value = &$this->getData($instr, $source);
        }

        return $value;
    }

    /* ------------------------------------------------------------------------
        Merge instructions parser
    ------------------------------------------------------------------------ */
    protected function parse($instructionString, $sep = " ")
    {
        $args = $this->explode(trim($instructionString), $sep);

        if (!count($args)) {
            throw new \Exception("Invalid Template instruction : no main argument provided in $instructionString");
        }

        $parser = new \StdClass();
        $parser->path = array_shift($args);
        $parser->source = $this->getSource($parser->path);

        $parser->params = array();

        if (!count($args)) {
            return $parser;
        }

        foreach ($args as $arg) {
            if (preg_match('#^(?<name>\w+)\s*(=(["\'])(?<value>(?:[^\3\\\\]|\\\\.)*)\3)$#', $arg, $pair)) {
                $parser->params[$pair['name']] = isset($pair['value']) ? $pair['value'] : null;
            } elseif ($arg[0] == "@") {
                $parser->params["attr"] = substr($arg, 1);
            } elseif ($arg[0] == "$") {
                $parser->params["var"] = substr($arg, 1);
            } elseif ($arg[0] == "/") {
                $parser->params["include"] = substr($arg, 1);
            } else {
                $parser->params["source"] = $arg;
            }
        }

        return $parser;
    }

    protected function getSource($data)
    {
        $source = array();
        $steps = $this->tokenize($data);
        for ($i = 0, $l = count($steps); $i < $l; $i++) {
            $step = $steps[$i];
            switch (true) {
                case $step == "":
                case $step == false:
                    if ($i == 0) {
                        $source[] = array('arg', '');
                    } else {
                        unset($step);
                    }
                    break;

                //case $step[0] == ".":
                //    $source[] = array('prop', substr($step, 1));
                //    break;

                case preg_match('#^\$(?<name>.*)$#', $step, $var):
                    $source[] = array('var', $var['name']);
                    break;

                case preg_match('#^(?<ext>\w+):(?<name>.*)$#', $step, $ext):
                    $source[] = array($ext['ext'], $ext['name']);
                    break;

                case preg_match('#^(?<name>[^(\/]+)\((?<params>.*)?\)$#', $step, $func):
                    $params = $this->explode($func['params'], ",");
                    $source[] = array('func', $func['name'], $params);
                    break;

                case preg_match('#^\[(?<name>(?<enc>["\'])?[^\2]*\2?)\]$#', $step, $offset):
                    $source[] = array('offset', $offset['name']);
                    break;

                case preg_match('#^\/(?<name>[^(]+)\((?<params>.*)?\)$#', $step, $method):
                    $params = $this->explode($method['params'], ",");
                    $source[] = array('method', $method['name'], $params);
                    break;

                default:
                    if ($i == 0) {
                        $source[] = array('source', $step);
                    } else {
                        $source[] = array('prop', $step);
                    }
            }
        }

        return $source;
    }

    protected function explode($str, $sep)
    {
        $l = strlen($str);
        $o = 0;
        $esc = false;
        $sq = false;
        $dq = false;
        $br = 0;
        $sbr = 0;
        $tok = array();

        for ($i = 0; $i < $l; $i++) {
            // Add token if separator found out of enclosures and brackets
            if ($str[$i] == $sep && !$dq && !$sq && !$br && !$sbr) {
                $tok[] = trim(substr($str, $o, $i - $o));
                $o = $i + 1;
                continue;
            }

            // Ignore character if escaped
            if ($esc) {
                $esc = false;
                continue;
            }

            // Special characters that affect parsing
            switch ($str[$i]) {
                case "'":
                    if (!$sq) {
                        $sq = true;
                    } else {
                        $sq = false;
                    }
                    break;
                case '"':
                    if (!$dq) {
                        $dq = true;
                    } else {
                        $dq = false;
                    }
                    break;
                case '(':
                    if (!$sq && !$dq) {
                        $br++;
                    }
                    break;
                case ')':
                    if (!$sq && !$dq) {
                        $br--;
                    }
                    break;
                case '[':
                    if (!$sq && !$dq) {
                        $sbr++;
                    }
                    break;
                case ']':
                    if (!$sq && !$dq) {
                        $sbr--;
                    }
                    break;
                case '\\':
                    $esc = true;
                    break;
            }
        }
        $tail = trim(substr($str, $o, $i - $o));
        if ($tail !== "") {
            $tok[] = $tail;
        }

        if ($sq || $dq || $br || $sbr || $esc) {
            throw new \Exception("Invalid string: unexpected end of string at offset $i");
        }

        return $tok;
    }

    protected function tokenize($str)
    {
        $l = strlen($str);
        $o = 0;
        $esc = false;
        $sq = false;
        $dq = false;
        $br = 0;
        $sbr = false;
        $steps = array();
        $step = false;

        // Function
        for ($i = 0; $i < $l; $i++) {
            // Tokenize only of out of enclosures
            if (!$dq && !$sq && !$br) {
                // Add token if dot found
                if ($str[$i] == ".") {
                    $steps[] = trim(substr($str, $o, $i - $o));
                    $o = $i + 1;
                    continue;
                }

                // Add token if opening square bracket
                if ($str[$i] == "[") {
                    $steps[] = trim(substr($str, $o, $i - $o));
                    $o = $i + 1;
                    $sbr = true;
                    continue;
                }

                // Add token enclosed by square brackets
                if ($str[$i] == "]" && $sbr) {
                    $steps[] = trim(substr($str, $o - 1, $i - $o + 2));
                    $o = $i + 1;
                    $sbr = false;
                    continue;
                }
            }

            // Ignore character if escaped
            if ($esc) {
                $esc = false;
                continue;
            }

            // Special characters that affect parsing
            switch ($str[$i]) {
                case "'":
                    if (!$sq) {
                        $sq = true;
                    } else {
                        $sq = false;
                    }
                    break;
                case '"':
                    if (!$dq) {
                        $dq = true;
                    } else {
                        $dq = false;
                    }
                    break;
                case '(':
                    if (!$sq && !$dq) {
                        $br++;
                    }
                    break;
                case ')':
                    if (!$sq && !$dq) {
                        $br--;
                    }
                    break;
                case '\\':
                    $esc = true;
                    break;
            }
        }
        $tail = trim(substr($str, $o, $i - $o));
        if ($tail !== false) {
            $steps[] = $tail;
        }

        if ($sq || $dq || $br || $sbr || $esc) {
            throw new \Exception("Invalid string: unexpected end of string at offset $i");
        }

        return $steps;
    }
}
