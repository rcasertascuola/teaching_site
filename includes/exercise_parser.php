<?php

class ExerciseParser {

    /**
     * Parses the wikitext of an exercise into a structured array of elements,
     * which can be either questions or content blocks.
     *
     * @param string $wikitext The raw text content of the exercise.
     * @return array A structured array of mixed content and question elements.
     */
    public function parse(string $wikitext): array {
        $elements = [];
        $question_order = 1;
        $last_pos = 0;

        // Find all question tags and their positions
        preg_match_all(
            '/\[\[(DOMANDA|DOMANDA_MULTI-RISPOSTA|DOMANDA_APERTA|COMPLETAMENTO_TESTO)\]\]/',
            $wikitext,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        foreach ($matches[0] as $index => $match) {
            $tag_full = $match[0]; // e.g., "[[DOMANDA]]"
            $tag_start_pos = $match[1];
            $tag_name = $matches[1][$index][0]; // e.g., "DOMANDA"

            // 1. Capture interstitial content before the current tag
            if ($tag_start_pos > $last_pos) {
                $content_text = trim(substr($wikitext, $last_pos, $tag_start_pos - $last_pos));
                if (!empty($content_text)) {
                    $elements[] = ['type' => 'content', 'text' => $content_text];
                }
            }

            // 2. Determine the content of the current question block
            $next_tag_start_pos = $matches[0][$index + 1][1] ?? strlen($wikitext);
            $question_block_content = substr($wikitext, $tag_start_pos, $next_tag_start_pos - $tag_start_pos);

            // 3. Parse the question block
            $parsed_question = null;
            $question_text_without_tag = substr($question_block_content, strlen($tag_full));

            switch ($tag_name) {
                case 'DOMANDA':
                    $parsed_question = $this->parseMultipleChoice($question_text_without_tag, false);
                    break;
                case 'DOMANDA_MULTI-RISPOSTA':
                    $parsed_question = $this->parseMultipleChoice($question_text_without_tag, true);
                    break;
                case 'DOMANDA_APERTA':
                    $parsed_question = $this->parseOpenEnded($question_text_without_tag);
                    break;
                case 'COMPLETAMENTO_TESTO':
                    $parsed_question = $this->parseClozeTest($question_text_without_tag);
                    break;
            }

            if ($parsed_question) {
                $parsed_question['order'] = $question_order++;
                $elements[] = ['type' => 'question', 'data' => $parsed_question];
            }

            $last_pos = $next_tag_start_pos;
        }

        // 4. Capture any trailing content after the last question
        if ($last_pos < strlen($wikitext)) {
            $trailing_content = trim(substr($wikitext, $last_pos));
            if (!empty($trailing_content)) {
                $elements[] = ['type' => 'content', 'text' => $trailing_content];
            }
        }

        return $elements;
    }

    private function parseMultipleChoice(string $content, bool $is_multi_response): ?array {
        $question = [
            'type' => $is_multi_response ? 'multiple_response' : 'multiple_choice',
            'text' => '',
            'points' => 0,
            'options' => []
        ];

        // Extract question text (first line after the tag)
        $lines = explode("\n", trim($content));
        $question['text'] = trim(array_shift($lines));

        // Extract points
        preg_match('/\[\[PUNTI\]\]\s*(\d+)/', $content, $points_match);
        $question['points'] = isset($points_match[1]) ? (int)$points_match[1] : 0;

        // Extract options
        $options_text = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*\*\s*([A-Z])\)\s*(.*)/', $line, $match)) {
                $options_text[$match[1]] = trim($match[2]);
            }
        }

        // Extract correct answer(s)
        $correct_answers = [];
        $correct_tag = $is_multi_response ? 'RISPOSTE_CORRETTE' : 'RISPOSTA_CORRETTA';
        if (preg_match('/\[\[' . $correct_tag . '\]\]\s*([A-Z,\s]+)/', $content, $answer_match)) {
            $correct_answers = array_map('trim', explode(',', $answer_match[1]));
        }

        if (empty($options_text) || empty($correct_answers)) return null;

        foreach ($options_text as $letter => $text) {
            $question['options'][] = [
                'text' => $letter . ') ' . $text,
                'is_correct' => in_array($letter, $correct_answers)
            ];
        }

        return $question;
    }

    private function parseOpenEnded(string $content): ?array {
        $question = [
            'type' => 'open_ended',
            'text' => '',
            'points' => 0,
            'char_limit' => null
        ];

        $lines = explode("\n", trim($content));
        $question['text'] = trim(array_shift($lines));

        preg_match('/\[\[PUNTI\]\]\s*(\d+)/', $content, $points_match);
        $question['points'] = isset($points_match[1]) ? (int)$points_match[1] : 0;

        preg_match('/\[\[LIMITE_CARATTERI\]\]\s*(\d+)/', $content, $limit_match);
        $question['char_limit'] = isset($limit_match[1]) ? (int)$limit_match[1] : null;

        if (empty($question['text'])) return null;

        return $question;
    }

    private function parseClozeTest(string $content): ?array {
        $question = [
            'type' => 'cloze_test',
            'text' => '',
            'points' => 0,
            'cloze_data' => ['word_list' => [], 'solution' => []]
        ];

        preg_match('/\[\[PUNTI\]\]\s*(\d+)/', $content, $points_match);
        $question['points'] = isset($points_match[1]) ? (int)$points_match[1] : 0;

        preg_match('/\[\[TESTO\]\]\s*(.*?)\s*\[\[ELENCO_PAROLE\]\]/s', $content, $text_match);
        $question['text'] = isset($text_match[1]) ? trim($text_match[1]) : '';

        preg_match('/\[\[ELENCO_PAROLE\]\]\s*(.*?)\s*\[\[SOLUZIONE\]\]/s', $content, $words_match);
        if(isset($words_match[1])) {
            $question['cloze_data']['word_list'] = array_map('trim', explode(',', trim($words_match[1])));
        }

        preg_match('/\[\[SOLUZIONE\]\]\s*(.*)/s', $content, $solution_match);
        if(isset($solution_match[1])) {
            $solution_lines = explode("\n", trim($solution_match[1]));
            foreach ($solution_lines as $line) {
                if(preg_match('/(\d+):\s*(.*)/', $line, $line_match)) {
                    $question['cloze_data']['solution'][trim($line_match[1])] = trim($line_match[2]);
                }
            }
        }

        if (empty($question['text']) || empty($question['cloze_data']['word_list']) || empty($question['cloze_data']['solution'])) return null;

        return $question;
    }
}
