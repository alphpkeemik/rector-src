<?php

declare(strict_types=1);

namespace Rector\TypeDeclaration\Rector\ClassMethod;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\NeverType;
use Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTypeChanger;
use Rector\BetterPhpDocParser\ValueObject\Type\SpacingAwareArrayTypeNode;
use Rector\Core\Rector\AbstractRector;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\PHPStanStaticTypeMapper\Enum\TypeKind;
use Symplify\Astral\TypeAnalyzer\ClassMethodReturnTypeResolver;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Rector\Tests\TypeDeclaration\Rector\ClassMethod\ArrayShapeFromConstantArrayReturnRector\ArrayShapeFromConstantArrayReturnRectorTest
 */
final class ArrayShapeFromConstantArrayReturnRector extends AbstractRector
{
    public function __construct(
        private readonly ClassMethodReturnTypeResolver $classMethodReturnTypeResolver,
        private readonly PhpDocTypeChanger $phpDocTypeChanger
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Add array shape exact types based on constant keys of array', [
            new CodeSample(
                <<<'CODE_SAMPLE'
final class SomeClass
{
    public function run(string $name)
    {
        return ['name' => $name];
    }
}
CODE_SAMPLE
,
                <<<'CODE_SAMPLE'
final class SomeClass
{
    /**
     * @return array{name: string}
     */
    public function run(string $name)
    {
        return ['name' => $name];
    }
}
CODE_SAMPLE
            ),
        ]);
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    /**
     * @param ClassMethod $node
     */
    public function refactor(Node $node): ?Node
    {
        /** @var Return_[] $returns */
        $returns = $this->betterNodeFinder->findInstanceOf($node, Return_::class);
        // exact one shape only
        if (count($returns) !== 1) {
            return null;
        }

        $return = $returns[0];
        if (! $return->expr instanceof Expr) {
            return null;
        }

        $returnExprType = $this->getType($return->expr);
        if (! $returnExprType instanceof ConstantArrayType) {
            return null;
        }

        // empty array
        if ($returnExprType->getKeyType() instanceof NeverType) {
            return null;
        }

        $returnType = $this->classMethodReturnTypeResolver->resolve($node, $node->getAttribute(AttributeKey::SCOPE));
        if ($returnType instanceof ConstantArrayType) {
            return null;
        }

        $phpDocInfo = $this->phpDocInfoFactory->createFromNodeOrEmpty($node);

        $returnExprTypeNode = $this->staticTypeMapper->mapPHPStanTypeToPHPStanPhpDocTypeNode(
            $returnExprType,
            TypeKind::RETURN()
        );

        if ($returnExprTypeNode instanceof GenericTypeNode) {
            return null;
        }

        if ($returnExprTypeNode instanceof SpacingAwareArrayTypeNode) {
            return null;
        }

        $hasChanged = $this->phpDocTypeChanger->changeReturnType($phpDocInfo, $returnExprType);
        if (! $hasChanged) {
            return null;
        }

        return $node;
    }
}
