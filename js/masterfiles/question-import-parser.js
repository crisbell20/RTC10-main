/**
 * Parses questionnaire text blocks (Word/Excel copy-paste) into import rows.
 *
 * Supported block format:
 *   What is the capital of the Philippines?
 *
 *   A. Cebu
 *   B. Davao
 *   C. Manila
 *   D. Iloilo
 *   E. Baguio
 *
 *   Answer: C
 */
(function (global) {
    'use strict';

    const OPTION_LINE = /^([A-E])[\.\):\-]\s*(.+)$/i;
    const QUESTION_LABEL = /^Question\s*:?\s*(.*)$/i;
    const Q_LABEL = /^Q(?:uestion)?\s*(\d+)?\s*[:.)]\s*(.*)$/i;
    const NUMBERED_LINE = /^(\d+)[\.\)]\s+(.+)$/;
    const ANSWER_LINE = /^(?:Answer|Correct(?:\s+Answer)?|Ans\.?)\s*:?\s*\(?([A-E])\)?\s*$/i;
    const POINTS_LINE = /^Points?\s*:?\s*(\d+(?:\.\d+)?)\s*$/i;
    const BLOCK_SPLIT = /\n(?=(?:Question\s*:?\s*(?:\n|$)|Q(?:uestion)?\s*\d*\s*[:.)]|(?:\d+\.)+\s+\S))/i;

    function normalizeText(text) {
        return String(text || '')
            .replace(/\uFEFF/g, '')
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n')
            .replace(/[\u2018\u2019]/g, "'")
            .replace(/[\u201C\u201D]/g, '"')
            .trim();
    }

    function blockHasAnswer(blockText) {
        return blockText
            .split('\n')
            .some(line => ANSWER_LINE.test(line.trim()));
    }

    function splitBlocks(text) {
        const normalized = normalizeText(text);
        if (!normalized) {
            return [];
        }

        const hasLegacyLabel = /^Question\s*:?\s*/im.test(normalized) || /^Q(?:uestion)?\s*\d*\s*[:.)]/im.test(normalized);
        if (hasLegacyLabel) {
            return normalized
                .split(/(?:\n---+\n|\n____+\n)/)
                .flatMap(chunk => chunk.split(BLOCK_SPLIT))
                .map(part => part.trim())
                .filter(Boolean);
        }

        const segments = normalized.split(/\n\s*\n+/);
        const blocks = [];
        let buffer = [];

        segments.forEach(segment => {
            buffer.push(segment);
            const combined = buffer.join('\n\n');
            if (blockHasAnswer(combined)) {
                blocks.push(combined.trim());
                buffer = [];
            }
        });

        if (buffer.length) {
            blocks.push(buffer.join('\n\n').trim());
        }

        return blocks.length ? blocks : [normalized];
    }

    function stripOptionMarker(text) {
        let value = String(text || '').trim();
        let markedCorrect = false;

        if (value.endsWith('*')) {
            markedCorrect = true;
            value = value.slice(0, -1).trim();
        }

        return { value, markedCorrect };
    }

    function parseBlock(blockText, blockIndex) {
        const errors = [];
        const lines = blockText.split('\n');

        const questionLines = [];
        const options = {};
        let correctAnswer = '';
        let inQuestion = false;

        for (const rawLine of lines) {
            const line = rawLine.trim();
            if (!line) {
                continue;
            }

            const questionLabel = line.match(QUESTION_LABEL);
            if (questionLabel) {
                inQuestion = true;
                if (questionLabel[1].trim()) {
                    questionLines.push(questionLabel[1].trim());
                }
                continue;
            }

            const qLabel = line.match(Q_LABEL);
            if (qLabel && !OPTION_LINE.test(line)) {
                inQuestion = true;
                if (qLabel[2] && qLabel[2].trim()) {
                    questionLines.push(qLabel[2].trim());
                }
                continue;
            }

            const optionMatch = line.match(OPTION_LINE);
            if (optionMatch) {
                inQuestion = false;
                const letter = optionMatch[1].toUpperCase();
                const parsedOption = stripOptionMarker(optionMatch[2]);
                options[letter] = parsedOption.value;
                if (parsedOption.markedCorrect) {
                    correctAnswer = letter;
                }
                continue;
            }

            const answerMatch = line.match(ANSWER_LINE);
            if (answerMatch) {
                correctAnswer = answerMatch[1].toUpperCase();
                continue;
            }

            if (POINTS_LINE.test(line)) {
                continue;
            }

            if (!Object.keys(options).length) {
                const numbered = line.match(NUMBERED_LINE);
                if (numbered && !questionLines.length) {
                    inQuestion = true;
                    questionLines.push(numbered[2].trim());
                    continue;
                }

                if (inQuestion || !questionLines.length) {
                    inQuestion = true;
                    questionLines.push(line);
                }
            }
        }

        const question = questionLines.join(' ').replace(/\s+/g, ' ').trim();

        if (!question) {
            errors.push('Question text is missing');
        }

        ['A', 'B', 'C', 'D'].forEach(letter => {
            if (!String(options[letter] || '').trim()) {
                errors.push(`Choice ${letter} is required`);
            }
        });

        if (!correctAnswer) {
            errors.push('Answer line is missing (e.g. "Answer: C") or mark the correct choice with *');
        } else if (!['A', 'B', 'C', 'D', 'E'].includes(correctAnswer)) {
            errors.push('Answer must be A, B, C, D, or E');
        } else if (!String(options[correctAnswer] || '').trim()) {
            errors.push(`Answer is ${correctAnswer} but choice ${correctAnswer} is empty`);
        }

        const row = {
            Question: question,
            Option_A: options.A || '',
            Option_B: options.B || '',
            Option_C: options.C || '',
            Option_D: options.D || '',
            Option_E: options.E || '',
            Correct_Answer: correctAnswer
        };

        return {
            blockNum: blockIndex + 1,
            row,
            errors,
            preview: question || blockText.split('\n')[0].trim()
        };
    }

    function parseQuestionnaireText(text) {
        const blocks = splitBlocks(text);
        const parsedBlocks = blocks.map((block, index) => parseBlock(block, index));

        const rows = [];
        const invalidBlocks = [];
        let validCount = 0;

        parsedBlocks.forEach(block => {
            if (block.errors.length) {
                invalidBlocks.push({
                    blockNum: block.blockNum,
                    errors: block.errors,
                    preview: block.preview
                });
            } else {
                validCount++;
                rows.push(block.row);
            }
        });

        return {
            rows,
            validCount,
            invalidBlocks,
            totalBlocks: parsedBlocks.length
        };
    }

    function getSampleQuestionnaireText() {
        return `What is the capital of the Philippines?
A. Cebu
B. Davao
C. Manila
D. Iloilo
E. Baguio

Answer: C

What is the capital of the Philippines?
A. Cebu
B. Davao
C. Manila
D. Iloilo
E. Baguio

Answer: C`;
    }

    global.QuestionImportParser = {
        parseQuestionnaireText,
        getSampleQuestionnaireText,
        normalizeText
    };
})(typeof window !== 'undefined' ? window : globalThis);
