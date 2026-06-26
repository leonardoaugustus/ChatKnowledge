<?php

namespace App\Enums;

enum KnowledgeType: string
{
    case Procedure = 'procedure';
    case Rule = 'rule';
    case Policy = 'policy';
    case Faq = 'faq';
    case IdealAnswer = 'ideal_answer';
    case Exception = 'exception';
    case Glossary = 'glossary';
    case Flow = 'flow';
    case OperationalStep = 'operational_step';

    /**
     * Get the display label for the knowledge type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Procedure => 'Procedimento',
            self::Rule => 'Regra',
            self::Policy => 'Política',
            self::Faq => 'FAQ',
            self::IdealAnswer => 'Resposta ideal',
            self::Exception => 'Exceção',
            self::Glossary => 'Glossário',
            self::Flow => 'Fluxo',
            self::OperationalStep => 'Passo operacional',
        };
    }

    /**
     * Get the Flux color token for the knowledge type.
     */
    public function color(): string
    {
        return match ($this) {
            self::Procedure => 'blue',
            self::Rule => 'indigo',
            self::Policy => 'purple',
            self::Faq => 'teal',
            self::IdealAnswer => 'green',
            self::Exception => 'red',
            self::Glossary => 'zinc',
            self::Flow => 'cyan',
            self::OperationalStep => 'sky',
        };
    }

    /**
     * Map a raw type string produced by the extractor agent to a known case.
     *
     * Accepts English and Portuguese variants (accents/spacing/casing tolerant)
     * and returns null when the value cannot be confidently mapped.
     */
    public static function fromExtractor(string $value): ?self
    {
        $normalized = str($value)->lower()->ascii()->replaceMatches('/[^a-z]+/', '_')->trim('_')->value();

        return match ($normalized) {
            'procedure', 'procedures', 'procedimento', 'procedimentos' => self::Procedure,
            'rule', 'rules', 'regra', 'regras' => self::Rule,
            'policy', 'policies', 'politica', 'politicas' => self::Policy,
            'faq', 'faqs', 'pergunta_frequente', 'perguntas_frequentes' => self::Faq,
            'ideal_answer', 'ideal_answers', 'resposta_ideal', 'respostas_ideais' => self::IdealAnswer,
            'exception', 'exceptions', 'excecao', 'excecoes' => self::Exception,
            'glossary', 'glossario', 'glossarios' => self::Glossary,
            'flow', 'flows', 'fluxo', 'fluxos' => self::Flow,
            'operational_step', 'operational_steps', 'step', 'steps', 'passo', 'passos', 'passo_operacional', 'etapa', 'etapas' => self::OperationalStep,
            default => self::tryFrom($normalized),
        };
    }
}
