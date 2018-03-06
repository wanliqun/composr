<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2018

 See text/EN/licence.txt for full licensing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    quizzes
 */

/**
 * Load the questions for a quiz into a single string.
 *
 * @param  AUTO_LINK $id The quiz ID
 * @return string The text string
 */
function load_quiz_questions_to_string($id)
{
    $text = '';
    $question_rows = $GLOBALS['SITE_DB']->query_select('quiz_questions', array('*'), array('q_quiz' => $id), 'ORDER BY q_order');
    foreach ($question_rows as $q) {
        $answer_rows = $GLOBALS['SITE_DB']->query_select('quiz_question_answers', array('*'), array('q_question' => $q['id']), 'ORDER BY q_order');
        $text .= get_translated_text($q['q_question_text']);
        $question_extra_text = get_translated_text($q['q_question_extra_text']);
        $text .= ' [' . $q['q_type'] . ']';
        if ($q['q_required'] == 1) {
            $text .= ' [REQUIRED]';
        }
        if ($q['q_marked'] == 0) {
            $text .= ' [UNMARKED]';
        }
        if ($question_extra_text != '') {
            $text .= "\n" . preg_replace('#^#m', ':', $question_extra_text);
        }
        foreach ($answer_rows as $a) {
            $text .= "\n" . get_translated_text($a['q_answer_text']) . (($a['q_is_correct'] == 1) ? ' [*]' : '');
            $explanation = get_translated_text($a['q_explanation']);
            if ($explanation != '') {
                $text .= "\n" . preg_replace('#^#m', ':', $explanation);
            }
        }
        $text .= "\n\n";
    }
    return $text;
}

/**
 * Parse a quiz question line, to find the question options.
 *
 * @param  string $question The quiz question line
 * @param  array $answers List of possible answers (used for validation purposes)
 * @param  string $question_extra_text The quiz question description
 * @param  boolean $do_validation Whether to perform validation / corrections
 * @return array A tuple: Question, question type, required?, marked?, question extra text (description)
 */
function parse_quiz_question_line($question, $answers, $question_extra_text = '', $do_validation = true)
{
    $question = trim($question);

    // Type?
    $type = ((count($answers) == 0) ? 'SHORT' : 'MULTIPLECHOICE');
    foreach (array('MULTIPLECHOICE', 'MULTIMULTIPLE', 'LONG', 'SHORT', 'SHORT_STRICT') as $possible_type) {
        if (strpos($question, ' [' . $possible_type . ']') !== false) {
            $type = $possible_type;
        }
        $question = str_replace(' [' . $possible_type . ']', '', $question);
    }

    // Required?
    $required = (strpos($question, ' [REQUIRED]') !== false) ? 1 : 0;
    $question = str_replace(' [REQUIRED]', '', $question);

    // Marked?
    $marked = (strpos($question, ' [UNMARKED]') !== false) ? 0 : 1;
    $question = str_replace(' [UNMARKED]', '', $question);

    // Some validation
    if ($do_validation) {
        if (($type == 'MULTIPLECHOICE' || $type == 'MULTIMULTIPLE') && (count($answers) == 0)) { // Error if multiple choice but no choices
            require_lang('quiz');
            attach_message(do_lang_tempcode('QUIZ_INVALID_MULTI_NO_ANSWERS'), 'warn');
            $type = 'SHORT';
        }
        if (($type == 'LONG') && (count($answers) > 0)) { // Error if any answers for LONG
            require_lang('quiz');
            attach_message(do_lang_tempcode('QUIZ_INVALID_LONG_HAS_ANSWERS'), 'warn');
            $type = 'MULTIPLECHOICE';
        }
        if (($marked == 0) && (count($answers) > 0)) {
            require_lang('quiz');
            attach_message(do_lang_tempcode('QUIZ_INVALID_UNMARKED_HAS_ANSWERS'), 'warn');
            $marked = 1;
        }
    }

    return array($question, $type, $required, $marked, $question_extra_text);
}

/**
 * Add the answers for a quiz.
 *
 * @param  AUTO_LINK $id The quiz ID
 * @param  string $text Text for questions
 * @param  ID_TEXT $type The type
 * @set COMPETITION TEST SURVEY
 *
 * @ignore
 */
function _save_available_quiz_answers($id, $text, $type)
{
    // Basic pre-parse
    $_qs = explode("\n\n", $text);
    $qs = array();
    foreach ($_qs as $q) {
        $q = trim($q);
        if ($q != '') {
            $qs[] = $q;
        }
    }
    $num_q = 0;

    // Try and bind to existing questions (if editing)
    $_existing = $GLOBALS['SITE_DB']->query_select('quiz_questions', array('*'), array('q_quiz' => $id), 'ORDER BY q_order');
    $qs2 = array();
    $existing = array();
    foreach ($qs as $i => $q) {
        $_as = explode("\n", $q);
        $as = array();
        foreach ($_as as $a) {
            if ($a != '') {
                $as[] = $a;
            }
        }

        $q = array_shift($as);
        $qs2[$i] = $q . "\n" . implode("\n", $as);

        list($question) = parse_quiz_question_line($q, $as, '', false);

        foreach ($_existing as $_i => $q_row) { // Try and match to an existing question, by the question name
            if (get_translated_text($q_row['q_question_text']) == $question) {
                $existing[$i] = $q_row;
                unset($_existing[$_i]);
                continue 2;
            }
        }
        $existing[$i] = null;
    }

    // Reassign DB slots for existing questions that were left unused, to new ones that were not matched
    foreach ($existing as $i => $e) {
        if ($e === null) {
            $existing[$i] = array_shift($_existing);
        }
    }
    // Even if there were more before, we will add them to what we're saving now - then erase later
    foreach ($_existing as $e) {
        $existing[] = $e;
    }

    // Parse out answers etc for each question
    foreach ($qs2 as $i => $q) {
        $_as = explode("\n", $q);
        $as = array();
        foreach ($_as as $a) {
            if ($a != '') {
                if (substr($a, 0, 1) == ':') { // Is an explanation
                    if (count($as) != 0) {
                        $as[count($as) - 1][1] = trim($as[count($as) - 1][1] . "\n" . trim(substr($a, 1)));
                    }
                } else {
                    $as[] = array($a, '');
                }
            }
        }

        if (count($as) == 0) {
            continue; // Was only an orphaned explanation, so ignore
        }

        $_q = array_shift($as);
        $question = $_q[0];
        $question_extra_text = $_q[1];
        list($question, $type, $required, $marked, $question_extra_text) = parse_quiz_question_line($question, $as, $question_extra_text);

        if ($existing[$i] === null) { // We're adding a new question on the end
            $map = array(
                'q_quiz' => $id,
                'q_type' => $type,
                'q_order' => $i,
                'q_required' => $required,
                'q_marked' => $marked,
            );
            $map += insert_lang_comcode('q_question_text', $question, 2);
            $map += insert_lang_comcode('q_question_extra_text', $question_extra_text, 2);
            $q_id = $GLOBALS['SITE_DB']->query_insert('quiz_questions', $map, true);

            // Now we add the answers
            foreach ($as as $x => $_a) {
                list($a, $explanation) = $_a;

                $is_correct = ((($x == 0) && (strpos($qs2[$i], ' [*]') === false) && ($type != 'SURVEY')) || (strpos($a, ' [*]') !== false)) ? 1 : 0;
                $a = str_replace(' [*]', '', $a);

                $map = array(
                    'q_question' => $q_id,
                    'q_is_correct' => $is_correct,
                    'q_order' => $x,
                );
                $map += insert_lang_comcode('q_answer_text', $a, 2);
                $map += insert_lang('q_explanation', $explanation, 2);
                $GLOBALS['SITE_DB']->query_insert('quiz_question_answers', $map);
            }
        } else { // We're replacing an existing question
            if (multi_lang_content()) {
                if ($existing[$i]['q_question_extra_text'] == 1) {
                    $existing[$i] += insert_lang('q_question_extra_text', '', 2); // Fix possible corruption
                }
            }

            $map = array(
                'q_quiz' => $id,
                'q_type' => $type,
                'q_order' => $i,
                'q_required' => $required,
                'q_marked' => $marked,
            );
            $map += lang_remap('q_question_text', $existing[$i]['q_question_text'], $question);
            $map += lang_remap('q_question_extra_text', $existing[$i]['q_question_extra_text'], $question_extra_text);
            $GLOBALS['SITE_DB']->query_update('quiz_questions', $map, array('id' => $existing[$i]['id']));

            // Now we add the answers
            $_existing_a = $GLOBALS['SITE_DB']->query_select('quiz_question_answers', array('*'), array('q_question' => $existing[$i]['id']), 'ORDER BY q_order');
            $existing_a = array();
            foreach ($as as $x => $_a) { // Try and match to an existing answer
                list($a, $explanation) = $_a;

                $a = str_replace(' [*]', '', $a);

                foreach ($_existing_a as $_x => $a_row) {
                    if (get_translated_text($a_row['q_answer_text']) == $a) {
                        $existing_a[] = $a_row;
                        unset($_existing_a[$_x]);
                        continue 2;
                    }
                }
                $existing_a[] = null;
            }
            foreach ($existing_a as $_x => $e) {
                if ($e === null) {
                    $existing_a[$_x] = array_shift($_existing_a);
                }
            }
            foreach ($_existing_a as $e) {
                $existing_a[] = $e;
            }
            foreach ($as as $x => $_a) {
                list($a, $explanation) = $_a;

                $is_correct = ((($x == 0) && (strpos($qs2[$i], ' [*]') === false)) || (strpos($a, ' [*]') !== false)) ? 1 : 0;
                $a = str_replace(' [*]', '', $a);

                if ($existing_a[$x] !== null) {
                    $map = array(
                        'q_is_correct' => $is_correct,
                        'q_order' => $x,
                    );
                    $map += lang_remap_comcode('q_answer_text', $existing_a[$x]['q_answer_text'], $a);
                    $map += lang_remap('q_explanation', $existing_a[$x]['q_explanation'], $explanation);
                    $GLOBALS['SITE_DB']->query_update('quiz_question_answers', $map, array('id' => $existing_a[$x]['id']), '', 1);
                } else {
                    $map = array(
                        'q_question' => $existing[$i]['id'],
                        'q_is_correct' => $is_correct,
                        'q_order' => $x,
                    );
                    $map += insert_lang_comcode('q_answer_text', $a, 2);
                    $map += insert_lang('q_explanation', $explanation, 2);
                    $GLOBALS['SITE_DB']->query_insert('quiz_question_answers', $map);
                }
            }
            // If there were more answers before, deleting extra ones
            if (count($existing_a) > count($as)) {
                for ($x = count($as); $x < count($existing_a); $x++) {
                    $GLOBALS['SITE_DB']->query_delete('quiz_question_answers', array('id' => $existing_a[$x]['id']), '', 1);
                }
            }
        }

        $num_q++;
    }

    // If there were more answers questions, deleting extra ones
    if (count($existing) > $num_q) {
        for ($x = $num_q; $x < count($existing); $x++) {
            $GLOBALS['SITE_DB']->query_delete('quiz_questions', array('id' => $existing[$x]['id']), '', 1);
        }
    }
}

/**
 * Add a quiz.
 *
 * @param  SHORT_TEXT $name The name of the quiz
 * @param  ?integer $timeout The number of minutes allowed for completion (null: NA)
 * @param  LONG_TEXT $start_text The text shown at the start of the quiz
 * @param  LONG_TEXT $end_text The text shown at the end of the quiz
 * @param  LONG_TEXT $end_text_fail The text shown at the end of the quiz on failure
 * @param  LONG_TEXT $notes Notes
 * @param  integer $percentage Percentage correctness required for competition
 * @param  ?TIME $open_time The time the quiz is opened (null: now)
 * @param  ?TIME $close_time The time the quiz is closed (null: never)
 * @param  integer $num_winners The number of winners for this if it is a competition
 * @param  integer $redo_time The minimum number of hours between attempts
 * @param  ID_TEXT $type The type
 * @set    SURVEY COMPETITION TEST
 * @param  BINARY $validated Whether this is validated
 * @param  string $text Text for questions
 * @param  ?MEMBER $submitter The member adding it (null: current member)
 * @param  integer $points_for_passing The number of points awarded for completing/passing the quiz/test
 * @param  ?AUTO_LINK $tied_newsletter Newsletter for which a member must be on to enter (null: none)
 * @param  BINARY $reveal_answers Whether to reveal correct answers after the quiz is complete, so that the answerer can learn from the experience
 * @param  BINARY $shuffle_questions Whether to shuffle questions, to make cheating a bit harder
 * @param  BINARY $shuffle_answers Whether to shuffle multiple-choice answers, to make cheating a bit harder
 * @param  ?TIME $add_time The add time (null: now)
 * @param  ?SHORT_TEXT $meta_keywords Meta keywords for this resource (null: do not edit) (blank: implicit)
 * @param  ?LONG_TEXT $meta_description Meta description for this resource (null: do not edit) (blank: implicit)
 * @return AUTO_LINK The ID
 */
function add_quiz($name, $timeout, $start_text, $end_text, $end_text_fail, $notes, $percentage, $open_time, $close_time, $num_winners, $redo_time, $type, $validated, $text, $submitter = null, $points_for_passing = 0, $tied_newsletter = null, $reveal_answers = 0, $shuffle_questions = 0, $shuffle_answers = 0, $add_time = null, $meta_keywords = '', $meta_description = '')
{
    require_code('global4');
    prevent_double_submit('ADD_QUIZ', null, $name);

    if ($submitter === null) {
        $submitter = get_member();
    }
    if ($add_time === null) {
        $add_time = time();
    }
    if ($open_time === null) {
        $open_time = time();
    }

    if (!addon_installed('unvalidated')) {
        $validated = 1;
    }
    $map = array(
        'q_timeout' => $timeout,
        'q_notes' => $notes,
        'q_percentage' => $percentage,
        'q_open_time' => $open_time,
        'q_close_time' => $close_time,
        'q_num_winners' => $num_winners,
        'q_redo_time' => $redo_time,
        'q_type' => $type,
        'q_validated' => $validated,
        'q_submitter' => $submitter,
        'q_add_date' => $add_time,
        'q_points_for_passing' => $points_for_passing,
        'q_tied_newsletter' => $tied_newsletter,
        'q_reveal_answers' => $reveal_answers,
        'q_shuffle_questions' => $shuffle_questions,
        'q_shuffle_answers' => $shuffle_answers,
    );
    $map += insert_lang('q_name', $name, 2);
    $map += insert_lang_comcode('q_start_text', $start_text, 2);
    $map += insert_lang_comcode('q_end_text', $end_text, 2);
    $map += insert_lang_comcode('q_end_text_fail', $end_text_fail, 2);
    $id = $GLOBALS['SITE_DB']->query_insert('quizzes', $map, true);

    _save_available_quiz_answers($id, $text, $type);

    require_code('content2');
    if (($meta_keywords == '') && ($meta_description == '')) {
        seo_meta_set_for_implicit('quiz', strval($id), array($name, $start_text), $start_text);
    } else {
        seo_meta_set_for_explicit('quiz', strval($id), $meta_keywords, $meta_description);
    }

    log_it('ADD_QUIZ', strval($id), $name);

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        generate_resource_fs_moniker('quiz', strval($id), null, null, true);
    }

    require_code('sitemap_xml');
    notify_sitemap_node_add('_SEARCH:quiz:do:' . strval($id), $add_time, null, SITEMAP_IMPORTANCE_MEDIUM, 'monthly', has_category_access($GLOBALS['FORUM_DRIVER']->get_guest_id(), 'quiz', strval($id)));

    return $id;
}

/**
 * Edit a quiz.
 *
 * @param  AUTO_LINK $id The ID
 * @param  SHORT_TEXT $name The name of the quiz
 * @param  ?integer $timeout The number of minutes allowed for completion (null: NA)
 * @param  LONG_TEXT $start_text The text shown at the start of the quiz
 * @param  LONG_TEXT $end_text The text shown at the end of the quiz
 * @param  LONG_TEXT $end_text_fail The text shown at the end of the quiz on failure
 * @param  LONG_TEXT $notes Notes
 * @param  integer $percentage Percentage correctness required for competition
 * @param  ?TIME $open_time The time the quiz is opened (null: now)
 * @param  ?TIME $close_time The time the quiz is closed (null: never)
 * @param  integer $num_winners The number of winners for this if it is a competition
 * @param  integer $redo_time The minimum number of hours between attempts
 * @param  ID_TEXT $type The type
 * @set    SURVEY COMPETITION TEST
 * @param  BINARY $validated Whether this is validated
 * @param  string $text Text for questions
 * @param  SHORT_TEXT $meta_keywords Meta keywords
 * @param  LONG_TEXT $meta_description Meta description
 * @param  integer $points_for_passing The number of points awarded for completing/passing the quiz/test
 * @param  ?AUTO_LINK $tied_newsletter Newsletter for which a member must be on to enter (null: none)
 * @param  BINARY $reveal_answers Whether to reveal correct answers after the quiz is complete, so that the answerer can learn from the experience
 * @param  BINARY $shuffle_questions Whether to shuffle questions, to make cheating a bit harder
 * @param  BINARY $shuffle_answers Whether to shuffle multiple-choice answers, to make cheating a bit harder
 * @param  ?TIME $add_time Add time (null: do not change)
 * @param  ?MEMBER $submitter Submitter (null: do not change)
 * @param  boolean $null_is_literal Determines whether some nulls passed mean 'use a default' or literally mean 'set to null'
 */
function edit_quiz($id, $name, $timeout, $start_text, $end_text, $end_text_fail, $notes, $percentage, $open_time, $close_time, $num_winners, $redo_time, $type, $validated, $text, $meta_keywords, $meta_description, $points_for_passing = 0, $tied_newsletter = null, $reveal_answers = 0, $shuffle_questions = 0, $shuffle_answers = 0, $add_time = null, $submitter = null, $null_is_literal = false)
{
    $rows = $GLOBALS['SITE_DB']->query_select('quizzes', array('*'), array('id' => $id), '', 1);
    if (!array_key_exists(0, $rows)) {
        warn_exit(do_lang_tempcode('MISSING_RESOURCE', 'quiz'));
    }
    $_name = $rows[0]['q_name'];
    $_start_text = $rows[0]['q_start_text'];
    $_end_text = $rows[0]['q_end_text'];
    $_end_text_fail = $rows[0]['q_end_text_fail'];

    if ($open_time === null) {
        $open_time = time();
    }

    if (!addon_installed('unvalidated')) {
        $validated = 1;
    }

    require_code('submit');
    $just_validated = (!content_validated('quiz', strval($id))) && ($validated == 1);
    if ($just_validated) {
        send_content_validated_notification('quiz', strval($id));
    }

    $update_map = array(
        'q_timeout' => $timeout,
        'q_notes' => $notes,
        'q_percentage' => $percentage,
        'q_open_time' => $open_time,
        'q_close_time' => $close_time,
        'q_num_winners' => $num_winners,
        'q_redo_time' => $redo_time,
        'q_type' => $type,
        'q_validated' => $validated,
        'q_points_for_passing' => $points_for_passing,
        'q_tied_newsletter' => $tied_newsletter,
        'q_reveal_answers' => $reveal_answers,
        'q_shuffle_questions' => $shuffle_questions,
        'q_shuffle_answers' => $shuffle_answers,
    );
    $update_map += lang_remap('q_name', $_name, $name);
    $update_map += lang_remap_comcode('q_start_text', $_start_text, $start_text);
    $update_map += lang_remap_comcode('q_end_text', $_end_text, $end_text);
    $update_map += lang_remap_comcode('q_end_text_fail', $_end_text_fail, $end_text_fail);

    if ($add_time !== null) {
        $update_map['q_add_date'] = $add_time;
    }
    if ($submitter !== null) {
        $update_map['q_submitter'] = $submitter;
    }

    $GLOBALS['SITE_DB']->query_update('quizzes', $update_map, array('id' => $id));

    if (!fractional_edit()) {
        _save_available_quiz_answers($id, $text, $type);
    }

    require_code('urls2');
    suggest_new_idmoniker_for('quiz', 'do', strval($id), '', $name);

    require_code('content2');
    seo_meta_set_for_explicit('quiz', strval($id), $meta_keywords, $meta_description);

    log_it('EDIT_QUIZ', strval($id), $name);

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        generate_resource_fs_moniker('quiz', strval($id));
    }

    require_code('sitemap_xml');
    notify_sitemap_node_edit('SEARCH:quiz:do:' . strval($id), has_category_access($GLOBALS['FORUM_DRIVER']->get_guest_id(), 'quiz', strval($id)));
}

/**
 * Delete a quiz.
 *
 * @param  AUTO_LINK $id The ID
 */
function delete_quiz($id)
{
    $rows = $GLOBALS['SITE_DB']->query_select('quizzes', array('*'), array('id' => $id), '', 1);
    if (!array_key_exists(0, $rows)) {
        warn_exit(do_lang_tempcode('MISSING_RESOURCE', 'quiz'));
    }
    $_name = $rows[0]['q_name'];
    $_start_text = $rows[0]['q_start_text'];
    $_end_text = $rows[0]['q_end_text'];
    $_end_text_fail = $rows[0]['q_end_text_fail'];
    $name = get_translated_text($_name);

    delete_lang($_name);
    delete_lang($_start_text);
    delete_lang($_end_text);
    delete_lang($_end_text_fail);

    require_code('content2');
    seo_meta_erase_storage('quiz', strval($id));

    $GLOBALS['SITE_DB']->query_delete('quizzes', array('id' => $id), '', 1);
    $GLOBALS['SITE_DB']->query_delete('quiz_member_last_visit', array('v_quiz_id' => $id));
    $GLOBALS['SITE_DB']->query_delete('quiz_winner', array('q_quiz' => $id));
    $questions = $GLOBALS['SITE_DB']->query_select('quiz_questions', array('*'), array('q_quiz' => $id));
    foreach ($questions as $question) {
        delete_lang($question['q_question_text']);
        delete_lang($question['q_question_extra_text']);
        $answers = $GLOBALS['SITE_DB']->query_select('quiz_question_answers', array('*'), array('q_question' => $question['id']));
        foreach ($answers as $answer) {
            delete_lang($answer['q_answer_text']);
            delete_lang($answer['q_explanation']);
        }
        $GLOBALS['SITE_DB']->query_delete('quiz_entry_answer', array('q_question' => $question['id']));
        $GLOBALS['SITE_DB']->query_delete('quiz_question_answers', array('q_question' => $question['id']));
    }
    $GLOBALS['SITE_DB']->query_delete('quiz_questions', array('q_quiz' => $id));
    $GLOBALS['SITE_DB']->query_delete('quiz_entries', array('q_quiz' => $id));

    update_catalogue_content_ref('quiz', strval($id), '');

    $GLOBALS['SITE_DB']->query_delete('group_category_access', array('module_the_name' => 'quiz', 'category_name' => strval($id)));

    log_it('DELETE_QUIZ', strval($id), $name);

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        expunge_resource_fs_moniker('quiz', strval($id));
    }

    require_code('sitemap_xml');
    notify_sitemap_node_delete('SEARCH:quiz:do:' . strval($id));
}
