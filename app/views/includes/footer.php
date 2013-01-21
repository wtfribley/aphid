	<div class="push"></div>
</div> <!-- wrapper -->
	
	<footer></footer>
	
<?php	
	if (isset($scripts)) {
		if (is_string($scripts)) $scripts = array($scripts);
		
		foreach ($scripts as $script) {
			if ($script == 'jquery') $script = '//ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js';	
?>
	<script src="<?= $script; ?>"></script>
<?php		
		}
	}
?>
	
</body>
</html>