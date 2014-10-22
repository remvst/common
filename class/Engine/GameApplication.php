<?php
namespace common\Engine;

/**
 * Class for games. This class does not really
 * provide any new features, but allows easier
 * StatisticsManager management.
 * You only need to redefine the getStatisticsManager()
 * method, and then you will be able to get charts, recap,
 * register stats, and get leaderboards.
 */
abstract class GameApplication extends Application implements Chartable,Overviewable{
	public abstract function getStatisticsManager();
	
	public function getCharts(){
		$statManager = $this->getStatisticsManager();
		$dailyStats = $statManager->getDailyStats();
		
		$chart = new \common\Util\RGraph('Daily stats for ' . $this->getName(),'Bar');
		
		$dates = array();
		$games = array();
		foreach($dailyStats as $s){
			$dates[] = $s['date'];
			$games[] = $s['games'];
		}
		$chart->setLabels($dates);
		$chart->setData($games);
		
		return array($chart);
	}
	
	public function getOverview(){
		$statManager = $this->getStatisticsManager();
		$dailyStats = $statManager->getDailyStats();
		
		$overviews = array(
			array(
				'title' => 'Daily games',
				'columns' => array('date','games'),
				'values' => $dailyStats
			)
		);
		return $overviews;
	}
	
	public function registerStats(){
		$params = $this->request->getParameters();
		if(isset($params['format']) && $params['format'] == 'json'){
			$format = 'json';
		}else{
			$format = 'text';
		}
		
		try{
			$statsManager = $this->getStatisticsManager();
			$game = $statsManager->process($this,$this->getRequest());
			
			// Getting the rank
			$rank = $statsManager->getRank($game);
			
			$res = array(
				'status' => 'success',
				'rank' => $rank,
				'entity' => $game->toArray(),
				'message' => 'You are ranked #' . $rank
			);
		}catch(Exception $e){
			$res = array(
				'status' => 'error',
				'message' => 'Error: ' . $e->getMessage()
			);
		}
		
		if($format == 'json'){
			return json_encode($res);
		}else{
			return $res['message'];
		}
	}
	
	public function getStats($columns,$page,$size,$where = null){
		// Récupération des top scores
		$statsManager = $this->getStatisticsManager();
		$games = $statsManager->getStats($columns,$page*$size,$size,null,$where);
		
        // Conversion en JSON
        $json_tab = array();
        $r = $page * $size;
        foreach($games as $g){
        	$line = array(
				'rank' => ++$r
        	);
			
			foreach($columns as $c){
				$getter = 'get' . ucwords($c);
				$line[$c] = utf8_encode($g->$getter());
			}
			
			$json_tab[] = $line;
        }
		
		return $json_tab;
	}
	
	/**
	 * Getting the cache manifest. This includes the following
	 * and folders :
	 * - js/
	 * - img/
	 * - css/
	 * - font/
	 * @return The cache manifest string.
	 */
	public function cacheManifest(){
		$manifest = new \common\Util\CacheManifest();
		$manifest->addDirectory(APP_EXEC_FOLDER . '/js');
		$manifest->addDirectory(APP_EXEC_FOLDER . '/img');
		$manifest->addDirectory(APP_EXEC_FOLDER . '/css');
		$manifest->addDirectory(APP_EXEC_FOLDER . '/font');
		$manifest->addDirectory(APP_EXEC_FOLDER . '/sound');
		
		$manifest->addFile('*','NETWORK');
		
		return $manifest->render($this->response);
	}
	
	/**
	 * Overriding errors to disable HTML (useful for popups).
	 */
	protected function showError($title,$message){
		return $title . ': ' . $message;
	}
}
