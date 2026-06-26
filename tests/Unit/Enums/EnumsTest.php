<?php

use App\Enums\AgentStatus;
use App\Enums\CurationStatus;
use App\Enums\DocumentStatus;
use App\Enums\KnowledgeType;
use App\Enums\Role;

it('exposes label and color tokens for each case', function (string $enum) {
    foreach ($enum::cases() as $case) {
        expect($case->label())->toBeString()->not->toBe('');
        expect($case->color())->toBeString()->not->toBe('');
    }
})->with([
    Role::class,
    AgentStatus::class,
    DocumentStatus::class,
    CurationStatus::class,
    KnowledgeType::class,
]);

it('maps extractor output strings to KnowledgeType', function (string $raw, ?KnowledgeType $expected) {
    expect(KnowledgeType::fromExtractor($raw))->toBe($expected);
})->with([
    'english value' => ['procedure', KnowledgeType::Procedure],
    'portuguese label' => ['Procedimento', KnowledgeType::Procedure],
    'snake case' => ['ideal_answer', KnowledgeType::IdealAnswer],
    'spaced portuguese' => ['Resposta Ideal', KnowledgeType::IdealAnswer],
    'accented portuguese' => ['exceção', KnowledgeType::Exception],
    'spaced english' => ['operational step', KnowledgeType::OperationalStep],
    'plural portuguese' => ['regras', KnowledgeType::Rule],
    'unknown value' => ['something the model invented', null],
]);
