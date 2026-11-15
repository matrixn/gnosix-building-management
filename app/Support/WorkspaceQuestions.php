<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkspaceQuestions
{
    public function schema(): array { return config('workspace'); }

    public function visible(array $field, array $answers): bool
    {
        foreach ($field['when'] ?? [] as $key => $condition) {
            $value = $answers[$key] ?? null;
            if (isset($condition['min'])) { if (! is_numeric($value) || $value < $condition['min']) return false; }
            elseif (! in_array($value, $condition, true)) return false;
        }
        return true;
    }

    public function resolve(array $state): array
    {
        $scenario = is_string($state['scenario'] ?? null) && isset($this->schema()['scenarios'][$state['scenario']]) ? $state['scenario'] : 'cabin';
        $defaults = $this->schema()['scenarios'][$scenario]['defaults'];
        $answers = ['scenario' => $scenario];
        foreach ($this->schema()['fields'] as $key => $field) {
            $value = $this->visible($field, $answers) ? ($state[$key] ?? $defaults[$key] ?? $field['default']) : $field['hidden_value'];
            $answers[$key] = $field['type'] === 'number' ? (int) $value : ($field['type'] === 'checks' ? (is_array($value) ? $value : []) : (string) $value);
        }
        return $answers;
    }

    public function validateInput(array $input): array
    {
        $rules = ['version' => ['required', 'integer', 'min:1'], 'step' => ['required', Rule::in($this->schema()['steps'])], 'guided' => ['required', 'boolean'], 'answers' => ['sometimes', 'array'], 'answers.scenario' => ['sometimes', 'string', Rule::in(array_keys($this->schema()['scenarios']))]];
        foreach ($this->schema()['fields'] as $key => $field) {
            if ($field['type'] === 'checks' && ($input['answers'][$key] ?? null) === '') $input['answers'][$key] = [];
            $rules['answers.'.$key] = array_merge(['sometimes', 'nullable'], match ($field['type']) {
                'number' => ['integer', 'min:'.$field['min'], 'max:'.$field['max']],
                'select' => ['string', Rule::in($this->schema()[$field['options']])],
                'checks' => ['array', 'max:20'],
                default => ['string', 'max:'.$field['max']],
            });
            if ($field['type'] === 'checks') $rules['answers.'.$key.'.*'] = ['string', 'distinct', Rule::in($this->schema()[$field['options']])];
        }
        $data = Validator::make($input, $rules, [], $this->attributes())->validate();
        $data['answers'] = array_intersect_key($data['answers'] ?? [], ['scenario' => true] + $this->schema()['fields']);
        return $data;
    }

    public function complete(array $state): array
    {
        $resolved = $this->resolve($state);
        $rules = [];
        foreach ($this->schema()['fields'] as $key => $field) {
            if (($field['required'] ?? false) && $this->visible($field, $resolved)) $rules['answers.'.$key] = ['required'];
        }
        Validator::make(['answers' => $resolved], $rules, [], $this->attributes())->validate();
        if ($this->plan($resolved)['nodes'] > $this->schema()['batch_node_limit']) {
            throw ValidationException::withMessages(['answers.room_count' => __('studio.batch_limit', ['limit' => $this->schema()['batch_node_limit']])]);
        }
        return $resolved;
    }

    public function plan(array $resolved): array
    {
        $properties = $resolved['property_count']; $buildings = $properties * $resolved['building_count'];
        $floors = $buildings * $resolved['floor_count'];
        $spaces = $properties * ($resolved['room_count'] + $resolved['dormitory_count'] + count($resolved['shared_facilities']));
        return ['properties' => $properties, 'buildings' => $buildings, 'floors' => $floors, 'spaces' => $spaces, 'beds' => $properties * $resolved['dormitory_count'] * $resolved['beds_per_dorm'], 'nodes' => $properties + $buildings + $floors + $spaces];
    }

    private function attributes(bool $nested = true): array
    {
        $attributes = [];
        foreach ($this->schema()['fields'] as $key => $field) $attributes[($nested ? 'answers.' : '').$key] = __('studio.field_'.$key);
        return $attributes;
    }
}
