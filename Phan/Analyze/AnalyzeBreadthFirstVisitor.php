<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\Configuration;
use \Phan\Debug;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\KindVisitorImplementation;
use \Phan\Language\Context;
use \Phan\Language\Element\{
    Clazz,
    Comment,
    Constant,
    Method,
    Property,
    Variable
};
use \Phan\Langauge\Type;
use \Phan\Language\FQSEN;
use \Phan\Language\Type\ArrayType;
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;

/**
 * # Example Usage
 * ```
 * $context =
 *     (new Element($node))->acceptKindVisitor(
 *         new AnalyzeBreadthFirstVisitor($context)
 *     );
 * ```
 */
class AnalyzeBreadthFirstVisitor extends KindVisitorImplementation {
    use \Phan\Language\AST;
    use \Phan\Analyze\ArgumentType;

    /**
     * @var Context
     * The context in which the node we're going to be looking
     * at exits.
     */
    private $context;

    /**
     * @var Node|null
     */
    private $parent_node;

    /**
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     *
     * @param Node|null $parent_node
     * The parent node of the node being analyzed
     */
    public function __construct(
        Context $context,
        Node $parent_node = null
    ) {
        $this->context = $context;
        $this->parent_node = $parent_node;
    }

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visit(Node $node) : Context {
        // Many nodes don't change the context and we
        // don't need to read them.
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitAssign(Node $node) : Context {
        if($node->children['var'] instanceof \ast\Node
            && $node->children['var']->kind == \ast\AST_LIST
        ) {
            // Get the type of the right side of the
            // assignment
            $right_type =
                UnionType::fromNode($this->context, $node);

            // Figure out the type of elements in the list
            $element_type =
                $right_type->asNonGenericTypes();

            foreach($node->children['var']->children as $child_node) {
                // Some times folks like to pass a null to
                // a list to throw the element away. I'm not
                // here to judge.
                if (!($child_node instanceof Node)) {
                    continue;
                }

                $variable = Variable::fromNodeInContext(
                    $child_node,
                    $this->context,
                    false
                );

                // Set the element type on each element of
                // the list
                $variable->setUnionType($element_type);

                // Note that we're not creating a new scope, just
                // adding variables to the existing scope
                $this->context->addScopeVariable($variable);
            }

            return $this->context;
        }

        // Get the type of the right side of the
        // assignment
        $right_type =
            UnionType::fromNode($this->context, $node->children['expr']);

        $variable = null;

        // Check to see if this is an array offset type
        // thing like '$a[] = 5'.
        if ($node->children['var'] instanceof Node
            && $node->children['var']->kind === \ast\AST_DIM
        ) {
            $variable_name =
                self::astVariableName($node->children['var']);

            // Check to see if the variable is not yet defined
            if ($this->context->getScope()->hasVariableWithName(
                $variable_name
            )) {
                $variable = $this->context->getScope()->getVariableWithName(
                    $variable_name
                );
            }

            // Make the right type a generic (i.e. int -> int[])
            $right_type = $right_type->asGenericTypes();
        } else {
            // Create a new variable
            $variable = Variable::fromNodeInContext(
                $node,
                $this->context
            );
        }

        // Set that type on the variable
        $variable->getUnionType()->addUnionType($right_type);

        // Note that we're not creating a new scope, just
        // adding variables to the existing scope
        $this->context->addScopeVariable($variable);

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitAssignRef(Node $node) : Context {
        return $this->visitAssign($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitList(Node $node) : Context {
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitIfElem(Node $node) : Context {
        // Just check for errors in the expression
        if (isset($node->children['cond'])
            && $node->children['cond'] instanceof Node
        ) {
            $expression_type = UnionType::fromNode(
                $this->context,
                $node->children['cond']
            );
        }

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitWhile(Node $node) : Context {
        return $this->visitIfElem($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitSwitch(Node $node) : Context {
        return $this->visitIfElem($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitSwitchCase(Node $node) : Context {
        return $this->visitIfElem($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitExprList(Node $node) : Context {
        return $this->visitIfElem($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitDoWhile(Node $node) : Context {
        /*
        node_type($file, $namespace, $ast->children[1], $current_scope, $current_class, $taint);
         */
        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_GLOBAL`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitGlobal(Node $node) : Context {
        $variable = Variable::fromNodeInContext(
            $node->children['var'],
            $this->context,
            false
        );

        // Note that we're not creating a new scope, just
        // adding variables to the existing scope
        $this->context->addScopeVariable($variable);

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitForeach(Node $node) : Context {
        $expression_type = UnionType::fromNode(
            $this->context,
            $node->children['expr']
        );

        // Check the expression type to make sure its
        // something we can iterate over
        if ($expression_type->isScalar()) {
            Log::err(
                Log::ETYPE,
                "$expression_type passed to foreach instead of array",
                $this->context->getFile(),
                $node->lineno
            );
        }

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitStatic(Node $node) : Context {
        $variable = Variable::fromNodeInContext(
            $node->children['var'],
            $this->context,
            false
        );

        // If the element has a default, set its type
        // on the variable
        if (isset($node->children['default'])) {
            $default_type = UnionType::fromNode(
                $this->context,
                $node->children['default']
            );

            $variable->setUnionType($default_type);
        }

        // Note that we're not creating a new scope, just
        // adding variables to the existing scope
        $this->context->addScopeVariable($variable);

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitEcho(Node $node) : Context {
        return $this->visitPrint($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitPrint(Node $node) : Context {
        $type = UnionType::fromNode(
            $this->context,
            $node->children['expr']
        );

        if ($type->isType(ArrayType::instance())
            || $type->isGeneric()
        ) {
            Log::err(
                Log::ETYPE,
                "array to string conversion",
                $this->context->getFile(),
                $node->lineno
            );
        }

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitVar(Node $node) : Context {
        $this->checkNoOp($node, "no-op variable");
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitArray(Node $node) : Context {
        $this->checkNoOp($node, "no-op array");
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitConst(Node $node) : Context {
        $this->checkNoOp($node, "no-op constant");
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitClosure(Node $node) : Context {
        $this->checkNoOp($node, "no-op closure");
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitReturn(Node $node) : Context {
        /*
        // a return from within a trait context is meaningless
        if($current_class['flags'] & \ast\flags\CLASS_TRAIT) break;
        // Check if there is a return type on the current function
        if(!empty($current_function['oret'])) {
            $ret = $ast->children[0];
            if($ret instanceof \ast\Node) {
                #	if($ast->children[0]->kind == \ast\AST_ARRAY) $ret_type='array';
                #	else $ret_type = node_type($file, $namespace, $ret, $current_scope, $current_class);
                $ret_type = node_type($file, $namespace, $ret, $current_scope, $current_class);
            } else {
                $ret_type = type_map(gettype($ret));
                // This is distinct from returning actual NULL which doesn't hit this else since it is an AST_CONST node
                if($ret_type=='null') $ret_type='void';
            }
            $check_type = $current_function['oret'];
            if(strpos("|$check_type|",'|self|')!==false) {
                $check_type = preg_replace("/\bself\b/", $current_class['name'], $check_type);
            }
            if(strpos("|$check_type|",'|static|')!==false) {
                $check_type = preg_replace("/\bstatic\b/", $current_class['name'], $check_type);
            }
            if(strpos("|$check_type|",'|\$this|')!==false) {
                $check_type = preg_replace("/\b\$this\b/", $current_class['name'], $check_type);
            }
            if(!type_check(all_types($ret_type), all_types($check_type), $namespace)) {
                Log::err(Log::ETYPE, "return $ret_type but {$current_function['name']}() is declared to return {$current_function['oret']}", $file, $ast->lineno);
            }
        } else {
            $lcs = strtolower($current_scope);
            $type = node_type($file, $namespace, $ast->children[0], $current_scope, $current_class);
            if(!empty($functions[$lcs]['oret'])) { // The function has a return type declared
                if(!type_check(all_types($type), all_types($functions[$lcs]['oret']), $namespace)) {
                    Log::err(Log::ETYPE, "return $type but {$functions[$lcs]['name']}() is declared to return {$functions[$lcs]['oret']}", $file, $ast->lineno);
                }
            } else {
                if(strpos($current_scope, '::') !== false) {
                    list($class_name,$method_name) = explode('::',$current_scope,2);
                    $idx = find_method_class($class_name, $method_name);
                    if($idx) {
                        $classes[$idx]['methods'][strtolower($method_name)]['ret'] = merge_type($classes[$idx]['methods'][strtolower($method_name)]['ret'], strtolower($type));
                    }
                } else {
                    if(!empty($functions[$lcs]['ret'])) {
                        $functions[$lcs]['ret'] = merge_type($functions[$lcs]['ret'], $type);
                    } else {
                        if($current_scope != 'global') {
                            $functions[$lcs]['ret'] = $type;
                        }
                    }
                }
            }
        }
         */

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitClassConstDecl(Node $node) : Context {
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitPropDecl(Node $node) : Context {
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitCall(Node $node) : Context {
        $expression = $node->children['expr'];

        if($expression->kind == \ast\AST_NAME) {
            $function_name = $expression->children['name'];

            $function_fqsen =
                $this->context->getScopeFQSEN()->withFunctionName(
                    $this->context,
                    $function_name
                );

            if (!$this->context->getCodeBase()->hasMethodWithFQSEN(
                $function_fqsen
            )) {
                Log::err(
                    Log::EUNDEF,
                    "call to undefined function {$function_name}()",
                    $this->context->getFile(),
                    $node->lineno
                );

                return $this->context;
            }

            $method = $this->context->getCodeBase()->getMethodByFQSEN(
                $function_fqsen
            );

            // Check the arguments and make sure they're cool.
            self::analyzeArgumentType($method, $node, $this->context);

            /*
            if (!$this->context->isInternal()) {
                // TODO:
                // re-check the function's ast with these args
                if(!$quick_mode) {
                    pass2($found['file'], $found['namespace'], $found['ast'], $found['scope'], $ast, $current_class, $found, $parent_scope);
                }
            } else {
                if(!$found) {
                    Log::err(
                        Log::EAVAIL,
                        "function {$function_name}() is not compiled into this version of PHP",
                        $this->context->getFile(),
                        $node->lineno
                    );
                }
            }
             */

            // Iterate through the arguments looking for arguments
            // that are not defined in this scope. If the method
            // takes a pass-by-reference parameter, then we add
            // the variable to the scope.
            $arguments = $node->children['args'];
            foreach ($arguments->children as $i => $argument) {
                // Look for variables passed as arguments
                if ($argument instanceof Node
                    && $argument->kind === \ast\AST_VAR
                ) {
                    $parameter = $method->getParameterList()[$i] ?? null;

                    // Check to see if the parameter at this
                    // position is pass-by-reference.
                    if (!$parameter || !$parameter->isPassByReference()) {
                        continue;
                    }

                    $variable_name =
                        self::astVariableName($argument);

                    // Check to see if the variable is not yet defined
                    if (!$this->context->getScope()->hasVariableWithName(
                        $variable_name
                    )) {
                        $variable = Variable::fromNodeInContext(
                            $argument,
                            $this->context,
                            false
                        );

                        // Set the element type on each element of
                        // the list
                        $variable->setUnionType(
                            $parameter->getUnionType()
                        );

                        // Note that we're not creating a new scope, just
                        // adding variables to the existing scope
                        $this->context->addScopeVariable($variable);
                    }
                }
            }

        } else if ($expression->kind == \ast\AST_VAR) {
            $name = self::astVariableName($expression);
            if(!empty($name)) {
                // $var() - hopefully a closure, otherwise we don't know
                if ($this->context->getScope()->hasVariableWithName(
                    $name
                )) {
                    $variable = $this->context->getScope()
                        ->getVariableWithName($name);

                    // TODO
                    /*
                    if(($pos=strpos($scope[$current_scope]['vars'][$name]['type'], '{closure '))!==false) {
                        $closure_id = (int)substr($scope[$current_scope]['vars'][$name]['type'], $pos+9);
                        $func_name = '{closure '.$closure_id.'}';
                        $found = $functions[$func_name];
                        arg_check($file, $namespace, $ast, $func_name, $found, $current_scope, $current_class);
                        if(!$quick_mode) pass2($found['file'], $found['namespace'], $found['ast'], $found['scope'], $ast, $current_class, $found, $parent_scope);
                    }
                     */
                }
            }
        }


        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitNew(Node $node) : Context {
        /*
        $class_name = find_class_name($file, $ast, $namespace, $current_class, $current_scope);
        if($class_name) {
            $method_name = '__construct';  // No type checking for PHP4-style constructors
            $method = find_method($class_name, $method_name);
            if($method) { // Found a constructor
                arg_check($file, $namespace, $ast, $method_name, $method, $current_scope, $current_class, $class_name);
                if($method['file'] != 'internal') {
                    // re-check the function's ast with these args
                    if(!$quick_mode) pass2($method['file'], $method['namespace'], $method['ast'], $method['scope'], $ast, $classes[strtolower($class_name)], $method, $parent_scope);
                }
            }
        }
         */

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitInstanceof(Node $node) : Context {
        /*
        $class_name = find_class_name($file, $ast, $namespace, $current_class, $current_scope);
         */

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitStaticCall(Node $node) : Context {
        /*
        $static_call_ok = false;
        $class_name = find_class_name($file, $ast, $namespace, $current_class, $current_scope, $static_call_ok);
        if($class_name) {
            // The class is declared, but does it have the method?
            $method_name = $ast->children[1];
            $static_class = '';
            if($ast->children[0]->kind == \ast\AST_NAME) {
                $static_class = $ast->children[0]->children[0];
            }

            $method = find_method($class_name, $method_name, $static_class);
            if(is_array($method) && array_key_exists('avail', $method) && !$method['avail']) {
                Log::err(Log::EAVAIL, "method {$class_name}::{$method_name}() is not compiled into this version of PHP", $file, $ast->lineno);
            }
            if($method === false) {
                Log::err(Log::EUNDEF, "static call to undeclared method {$class_name}::{$method_name}()", $file, $ast->lineno);
            } else if($method != 'dynamic') {
                // Was it declared static?
                if(!($method['flags'] & \ast\flags\MODIFIER_STATIC)) {
                    if(!$static_call_ok) {
                        Log::err(Log::ESTATIC, "static call to non-static method {$class_name}::{$method_name}() defined at {$method['file']}:{$method['lineno']}", $file, $ast->lineno);
                    }
                }
                arg_check($file, $namespace, $ast, $method_name, $method, $current_scope, $current_class, $class_name);
                if($method['file'] != 'internal') {
                    // re-check the function's ast with these args
                    if(!$quick_mode) pass2($method['file'], $method['namespace'], $method['ast'], $method['scope'], $ast, $classes[strtolower($class_name)], $method, $parent_scope);
                }
            }
        }
        */

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitMethodCall(Node $node) : Context {
        /*
        $class_name = find_class_name($file, $ast, $namespace, $current_class, $current_scope);
        if($class_name) {
            $method_name = $ast->children[1];
            $method = find_method($class_name, $method_name);
            if($method === false) {
                Log::err(Log::EUNDEF, "call to undeclared method {$class_name}->{$method_name}()", $file, $ast->lineno);
            } else if($method != 'dynamic') {
                if(array_key_exists('avail', $method) && !$method['avail']) {
                    Log::err(Log::EAVAIL, "method {$class_name}::{$method_name}() is not compiled into this version of PHP", $file, $ast->lineno);
                }
                arg_check($file, $namespace, $ast, $method_name, $method, $current_scope, $current_class, $class_name);
                if($method['file'] != 'internal') {
                    // re-check the function's ast with these args
                    if(!$quick_mode) pass2($method['file'], $method['namespace'], $method['ast'], $method['scope'], $ast, $classes[strtolower($class_name)], $method, $parent_scope);
                }
            }
        }
         */

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to check to see if its a no-op
     *
     * @param string $message
     * A message to emit if its a no-op
     *
     * @return null
     */
    private function checkNoOp(Node $node, string $message) {
        if($this->parent_node instanceof Node &&
            $this->parent_node->kind == \ast\AST_STMT_LIST
        ) {
            Log::err(
                Log::ENOOP,
                $message,
                $this->context->getFile(),
                $node->lineno
            );
        }

    }

}
