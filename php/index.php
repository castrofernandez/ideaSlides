<?php
	$DB_NAME = "slides.db";
	
	require 'Slim/Slim.php';
	\Slim\Slim::registerAutoloader();

	$app = new \Slim\Slim();
	
	$app->get('/test', function () {
		echo "OK";
	});
	
	/* ************* */
	/* Presentations */
	/* ************* */
	
	/* GET ALL */

	$app->get('/presentations', function () {
		$db = new SQLite3("../data/" . "slides.db");
		
		$results = $db->query('SELECT * FROM presentations ORDER BY sort, id');

		$presentations = array();
		$slides = array();
		
		$records = array();
		
		while ($row = $results->fetchArray()) {
			$presentation_id = $row['id'];
			
			$r = $db->query("SELECT *, (SELECT count(*) FROM slides b  WHERE a.id >= b.id AND presentation = $presentation_id) AS number, (SELECT count(id) FROM slides WHERE presentation = $presentation_id) AS total FROM slides a WHERE presentation = $presentation_id ORDER BY sort, id");
			
			$ids = array();
			
			$previous_slide = null;
			$i = 0;	
			
			while ($s = $r->fetchArray()) {
				$s['previous'] = $previous_slide['id'];
				$s['next'] = null;
				
				$ids[] = $s['id'];
				array_push($slides, $s);
				
				if (!empty($previous_slide))
					$slides[$i - 1]['next'] = $s['id'];
				
				$previous_slide = $s;
				$i++;
			}
			
			$row['slides'] = $ids;
			$presentations[] = $row;
			
			$records[] = $row;
		}

		$struct = array("presentation" => $records, "slides" => $slides);
		print json_encode($struct);
	});
	
	/* GET */
	
	$app->get('/presentations/:id', function ($presentation_id) {
		$db = new SQLite3("../data/" . "slides.db");
		
		$presentation = $db->query("SELECT *, (SELECT count(*) FROM presentations b  WHERE a.id >= b.id) AS number, (SELECT count(id) FROM presentations) AS total FROM presentations a WHERE id = $presentation_id");

		$record = $presentation->fetchArray();
		
		$slides = $db->query("SELECT *, (SELECT count(*) FROM slides b  WHERE a.id >= b.id AND presentation = $presentation_id) AS number, (SELECT count(id) FROM slides WHERE presentation = $presentation_id) AS total FROM slides a WHERE presentation = $presentation_id ORDER BY sort, id");
		
		$slide_ids = array();
		$records = array();
		
		$previous = null;
		$next = null;
		$i = 0;				
		
		while ($slide = $slides->fetchArray()) {
			$slide['previous'] = $previous['id'];
				
			$records[] = $slide;
			$slide_id = $slide['id'];
			array_push($slide_ids, $slide_id);
				
			if (!empty($previous))
				$records[$i - 1]['next'] = $slide['id'];
				
			$previous = $slide;
			$i++;
		}
		
		if (count($records) > 0)
			$records[$i - 1]['next'] = null;
		
		$record['slides'] = $slide_ids;
		
		$struct = array("presentation" => $record, "slides" => $records);
		print json_encode($struct);
	});
	
	/* POST */
	
	$app->post('/presentations', function () use ($app) {
		$presentation = $app->request()->getBody();
		$presentation = json_decode($presentation, true)['presentation'];
		
		$title = $presentation['title'];
		$description = $presentation['description'];
		
		$db = new SQLite3(realpath("../data/" . "slides.db"));
		
		$db->exec("INSERT INTO presentations (title, description) VALUES ('$title', '$description')");
		
		$result = $db->query('SELECT *, (SELECT count(*) FROM presentations b  WHERE a.id >= b.id) AS number, (SELECT count(id) FROM presentations) AS total FROM presentations a WHERE a.id IN (SELECT MAX(id) FROM presentations)');

		$record = $result->fetchArray();
		
		$id = $record['id'];
		$sort = $record['number'];
		
		$db->exec("UPDATE presentations SET sort = $sort WHERE id = $id");
		
		$struct = array("presentation" => $record);
		print json_encode($struct);
	});
	
	/* PUT */
	
	$app->put('/presentations/:id', function ($id) use ($app) {
		$presentation = $app->request()->getBody();
		$presentation = json_decode($presentation, true)['presentation'];
		
		$title = $presentation['title'];
		$description = $presentation['description'];
		$sort = $presentation['sort'];
		
		$db = new SQLite3("../data/" . "slides.db");
	
		$db->exec("UPDATE presentations SET title = '$title', description = '$description', sort = $sort WHERE id = $id");
	});
	
	/* DELETE */
	
	$app->delete('/presentations/:id', function ($id) {
		$db = new SQLite3("../data/" . "slides.db");
	
		$db->exec("DELETE FROM slides WHERE presentation = $id");
		$db->exec("DELETE FROM presentations WHERE id = $id");
	});	
	
	/* ****** */
	/* Slides */
	/* ****** */

	/* GET ALL */

	$app->get('/slides', function () {
		$db = new SQLite3("../data/" . "slides.db");
		
		$results = $db->query("SELECT *, (SELECT count(*) FROM slides b  WHERE a.id >= b.id) AS number, (SELECT count(id) FROM slides) AS total FROM slides a ORDER BY sort, id");

		$records = array();
		
		$previous = null;
		$next = null;
		$i = 0;
		
		while ($row = $results->fetchArray()) {
			$row['previous'] = $previous['id'];
			
			$records[] = $row;
			
			if (!empty($previous))
				$records[$i - 1]['next'] = $row['id'];
			
			$previous = $row;
			$i++;
		}
		
		if (count($records) > 0)
			$records[$i - 1]['next'] = null;

		$struct = array("slide" => $records);
		print json_encode($struct);
	});
	
	/* GET */
	
	$app->get('/slides/:id', function ($id) {
		$db = new SQLite3("../data/" . "slides.db");
		
		$result = $db->query("SELECT *, (SELECT count(*) FROM slides b  WHERE a.id >= b.id) AS number, (SELECT count(id) FROM slides) AS total FROM slides a WHERE id = $id");

		$record = $result->fetchArray();
		
		// Previous
		$result = $db->query("SELECT MAX(id) AS previous FROM slides WHERE id < $id");
		$record['previous'] = $result->fetchArray()['previous'];
		
		// Next
		$result = $db->query("SELECT MIN(id) AS next FROM slides WHERE id > $id");
		$record['next'] = $result->fetchArray()['next'];

		$struct = array("slide" => $record);
		print json_encode($struct);
	});
	
	/* POST */
	
	$app->post('/slides', function () use ($app) {
		$slide = $app->request()->getBody();
		$slide = json_decode($slide, true)['slide'];
		
		$title = $slide['title'];
		$category = $slide['category'];
		$body = $slide['body'];
		$code = $slide['code'];
		$presentation_id = $slide['presentation'];
	
		$db = new SQLite3(realpath("../data/" . "slides.db"));
		
		$db->exec("INSERT INTO slides (title, category, body, code, presentation) VALUES ('$title', '$category', '$body', '$code', $presentation_id)");
		
		$result = $db->query("SELECT *, (SELECT count(*) FROM slides b  WHERE a.id >= b.id AND presentation = $presentation_id) AS number, (SELECT count(id) FROM slides WHERE presentation = $presentation_id) AS total FROM slides a WHERE a.id IN (SELECT MAX(id) FROM slides WHERE presentation = $presentation_id) AND presentation = $presentation_id");

		$record = $result->fetchArray();
		
		$id = $record['id'];
		$sort = $record['number'];
		
		$db->exec("UPDATE slides SET sort = $sort WHERE id = $id");
		
		// Previous
		$result = $db->query("SELECT MAX(id) AS previous FROM slides WHERE id < (SELECT MAX(id) FROM slides) AND presentation = $presentation_id");
		$record['previous'] = $result->fetchArray()['previous'];
		
		// Next
		$record['next'] = null;

		$struct = array("slide" => $record);
		print json_encode($struct);
	});
	
	/* PUT */
	
	$app->put('/slides/:id', function ($id) use ($app) {
		$slide = $app->request()->getBody();
		$slide = json_decode($slide, true)['slide'];
		
		$title = $slide['title'];
		$category = $slide['category'];
		$body = $slide['body'];
		$code = $slide['code'];
		$sort = $slide['sort'];
		
		$db = new SQLite3("../data/" . "slides.db");
		
		if (empty($sort)) {			
			$slide = $db->query("SELECT (SELECT count(*) FROM slides b  WHERE a.id >= b.id) AS number FROM slides a WHERE id = $id");
			$sort = $slide['number'];
		}
	
		$db->exec("UPDATE slides SET title = '$title', category = '$category', body = '$body', code = '$code', sort = $sort WHERE id = $id");
	});
	
	/* DELETE */
	
	$app->delete('/slides/:id', function ($id) {
		$db = new SQLite3("../data/" . "slides.db");
	
		$db->exec("DELETE FROM slides WHERE id = $id");
	});
	
	$app->run();
?>