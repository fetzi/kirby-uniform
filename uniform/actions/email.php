<?php

/*
 * The action to send the form data as an email.
 */
uniform::$actions['email'] = function($form, $actionOptions)
{
	// the form could contain arrays which are incompatible with the template function
	$templatableItems = array_filter($form, function ($item) {
		return is_scalar($item);
	});

	$options = array(
		// apply the dynamic subject (insert form data)
		'subject'         => str::template(
			a::get($actionOptions, 'subject', l::get('uniform-email-subject')),
			$templatableItems
		),
		'snippet'         => a::get($actionOptions, 'snippet', false),
		'receive-copy'    => a::get($actionOptions, 'receive-copy', true),
		'to'              => a::get($actionOptions, 'to'),
		'sender'          => a::get($actionOptions, 'sender'),
		'service'         => a::get($actionOptions, 'service', 'mail'),
		'service-options' => a::get($actionOptions, 'service-options', array())
	);

	// remove newlines to prevent malicious modifications of the email
	// header
	$options['subject'] = str_replace("\n", '', $options['subject']);

	$mailBody = "";
	$snippet = $options['snippet'];

	if (empty($snippet))
	{
		foreach ($form as $key => $value)
		{
			if (str::startsWith($key, '_')) continue;
			if (is_array($value)) {
				$value = implode(', ', array_filter($value, function ($i) {
					return $i !== '';
				}));
			}
			$mailBody .= ucfirst($key).': '.$value."\n\n";
		}
	}
	else
	{
		$mailBody = snippet($snippet, compact('form', 'options'), true);
		if ($mailBody === false)
		{
			throw new Exception('Uniform email action: The email snippet "'.
				$snippet.'" does not exist!');
		}
	}

	$params = array(
		'service' => $options['service'],
		'options' => $options['service-options'],
		'to'      => $options['to'],
		'from'    => $options['sender'],
		'replyTo' => a::get($form, '_from'),
		'subject' => $options['subject'],
		'body'    => $mailBody
	);

	$email = email($params);

	try {
		$email->send();

		if ($options['receive-copy'] && array_key_exists('_receive_copy', $form))
		{
			$params['subject'] = l::get('uniform-email-copy').' '.$params['subject'];
			$params['to'] = $params['replyTo'];
			email($params)->send();
		}
	} catch (Error $e) {
		return array(
			'success' => false,
			'message' => l::get('uniform-email-error').' '.$e->getMessage()
		);
	}

	return array(
		'success' => true,
		'message' => l::get('uniform-email-success')
	);
};
