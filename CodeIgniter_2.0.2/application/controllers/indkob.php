<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Indkob extends CI_Controller {

    function __construct()
    {
        parent::__construct();
        $this->load->library('javascript');
		$this->load->helper('menu');
		$this->load->helper('url');
		$this->load->model('Permission');
		$this->load->model('Memberinfo');
    }

    function index() {
		if (! intval($this->session->userdata('uid')) > 0)
			redirect('/login');		
        $this->jquery->script('/ressources/jquery-1.6.2.min.js', TRUE);
        $this->javascript->compile();

		$permissions = $this->session->userdata('permissions');
		if (! $this->Memberinfo->checkgrouppermission($permissions, utf8_encode('F�lles indk�bsgruppe')))
		redirect('/minside');		

		$permissions = $this->session->userdata('permissions');

		$createsel = '';
		$createfsel = '';
		$adminsel = '';
		$cashsel = '';
		$excelsel = '';
		$dagenssalg = '';
		$nyemedlemmer = '';
		$welcome = '';
		$this->db->select('divisions.name, divisions.uid');
		$this->db->from('divisions');
		$this->db->where('type','aktiv');
		$this->db->order_by('divisions.name'); 
		$query = $this->db->get();

		foreach ($query->result_array() as $row)
		{
			$createsel .= '<option value="' . $row['uid'] . '">' . $row['name'] . "</option>\n";
			$createfsel .= $this->_getfuturepickupdays($row['uid']);
		}

		$bagdays = '';
		$this->db->select('id, explained');
		$this->db->from('producttypes');
		$this->db->where('bag','Y');
		$this->db->where('id !=',FF_GROCERYBAG);
		$this->db->order_by('sortkey'); 
		$query = $this->db->get();
		$bagdays = $query->result_array();
		$q2 = $this->db->query('select id, explained from ff_producttypes where bag = "Y" and id != 47');
		$bagdays = $q2->result_array();
		$content = '';
		
		$data = array(
               'title' => 'KBHFF Administrationsside',
               'heading' => 'KBHFF Administrationsside (demo): INDK&Oslash;B',
               'content' => $content,
			   'bagdays' => $bagdays,
               'createsel' => $createsel,
               'createfsel' => $createfsel,
          );

		$this->load->view('v_indkob', $data);
    }

    function dag() {
		if (! intval($this->session->userdata('uid')) > 0)
			redirect('/login');		
        $this->jquery->script('/ressources/jquery-1.6.2.min.js', TRUE);
        $this->javascript->compile();

		$permissions = $this->session->userdata('permissions');
		if (! $this->Memberinfo->checkgrouppermission($permissions, utf8_encode('F�lles indk�bsgruppe')))
		redirect('/minside');		

		if ($this->uri->segment(3) > 0)
		{
			$pickupdate = $this->uri->segment(3);
		} else {
			$pickupdate = $this->input->post('pickupdate');
		}

		$bagdays = '';
		$q2 = $this->db->query('select id, explained from ff_producttypes where bag = "Y" order by sortkey');
		$bagdays = $q2->result_array();

		$bdsel1 = '';
		$bdsel2 = '';
		$res = '<table><tr><td><strong>Afdeling</strong></td>';
		foreach ($bagdays as $bagday)
		{
			$res .= '<td><strong>' . $bagday['explained'] . '</strong></td>';
		}
		$res .= '</tr>';
		$this->db->select('divisions.uid as uid,divisions.name, pickupdates.pickupdate, pickupdates.uid as pickupdateuid');
		$this->db->from('divisions');
		$this->db->from('pickupdates');
		$this->db->where('divisions.uid = ff_pickupdates.division'); 
		$this->db->where('pickupdates.pickupdate ="' . addslashes($pickupdate) .'"'); 
		$this->db->order_by('divisions.name'); 
		$query = $this->db->get();
		foreach ($query->result_array() as $row)
		{
			$res .= $this->_getdiv($pickupdate, $bagdays, $row['uid']);
		}
		$res .= '</table>';



		$data = array(
               'title' => 'Bestilte poser ' . $pickupdate,
               'heading' => 'Bestilte poser ' . $pickupdate,
			   'bagdays' => $bagdays,
			   'totalorder' => $res,
               'pickupdate' => $pickupdate,
          );

		$this->load->view('v_indkob_dag', $data);
    }
	
    function leverandor() {
		if (! intval($this->session->userdata('uid')) > 0)
			redirect('/login');		
        $this->jquery->script('/ressources/jquery-1.6.2.min.js', TRUE);
        $this->javascript->compile();

		$permissions = $this->session->userdata('permissions');
//		if (! $this->Memberinfo->checkgrouppermission($permissions, utf8_encode('F�lles indk�bsgruppe')))
//		redirect('/minside');		
		$medlemsnummer = intval($this->session->userdata('uid'));
		$divisioninfo = $this->Memberinfo->division_info($medlemsnummer);
		$adress = $this->Memberinfo->retrieve_by_medlemsnummer($medlemsnummer);
		$firstname = $adress['firstname'];

		$data = array(
               'title' => 'F&aelig;lles indk&oslash;bsgruppes leverand&oslash;rsystem',
               'heading' => 'F&aelig;lles Indk&oslash;bsgruppes leverand&oslash;rsystem<br>' . $firstname,
			   'totalorder' => $res,
               'pickupdate' => $pickupdate,
          );

		$this->load->view('v_indkob_leverandor', $data);
    }

	
	
	function _getdiv($pickupdate, $bagdays, $division)	
	{
			$divisionname = $this->_divisionname($division);
			$totalorder = '<tr><td>' . $divisionname . '</td>';
			foreach ($bagdays as $bagday)
			{
				$totalorder .= '<td align="right">';
				$totalorder .= $this->_getcount(0, 'total',$bagday['id'], $pickupdate, $division);
				$totalorder .= '</td>';
			}
			$totalorder .= '</tr>' . "\n";

			return $totalorder;
	}

	function _getcount($divisionday, $status, $item, $pickupdate = '', $division = 0)	
	{
		switch ($status) {
			case 'udleveret' :
				$limit = 'AND ff_orderlines.status2 = "udleveret"';
				break;
			case 'ikkeudleveret' :
				$limit = 'AND ff_orderlines.status2 <> "udleveret"';
				break;
			case 'total' :
				$limit = '';
				break;
		}
		$datesel = 'AND ff_pickupdates.uid = 0';
		if ( $divisionday > 0)
		{
			$datesel = 'AND ff_pickupdates.uid = ' . (int)$divisionday;
		}
		if ($pickupdate > '')
		{
			$datesel = 'AND ff_pickupdates.pickupdate = "' . addslashes($pickupdate) .'" and ff_pickupdates.division = ' . $division;
		}
		
		$query = $this->db->query('SELECT sum( ff_orderlines.quant ) as sum, ff_orderlines.item, ff_items.units, ff_items.measure, ff_producttypes.explained
		FROM ff_orderlines, ff_orderhead, ff_items, ff_producttypes, ff_pickupdates, ff_divisions
		WHERE ff_orderlines.orderno = ff_orderhead.orderno
		AND (
		(
		ff_orderhead.status1 = "kontant"
		)
		OR (
		ff_orderhead.status1 = "nets"
		)
		)
		AND ff_orderlines.item = ff_items.id
		AND ff_items.producttype_id = ' . (int)$item . '
		AND ff_items.producttype_id = ff_producttypes.id
		AND ff_orderlines.iteminfo = ff_pickupdates.uid
		AND ff_divisions.uid = ff_pickupdates.division
		AND ff_pickupdates.division = ff_items.division
		' . $limit . ' ' . $datesel . '
		GROUP BY ff_orderlines.item
		ORDER BY ff_orderlines.item');
		
		$row = $query->row();
		if ($query->num_rows() > 0)
		{
		return ($row->sum);
		} else {
			return 0;
		}
	}
	
	private function _divisionname($division)
	{
			$this->db->select('name');
			$this->db->from('divisions');
			$this->db->where('uid', (int)$division); 
			$query = $this->db->get();
			$row = $query->row();
			return $row->name;
	}
	
	private function _division($divisionday)
	{
			$this->db->select('division');
			$this->db->from('pickupdates');
			$this->db->where('uid', (int)$divisionday); 
			$query = $this->db->get();
			$row = $query->row();
			return $row->division;
	}

	private function _pickupday($divisionday)
	{
			$this->db->select('pickupdate');
			$this->db->from('pickupdates');
			$this->db->where('uid', (int)$divisionday); 
			$query = $this->db->get();
			$row = $query->row();
			return $row->pickupdate;
	}
	

	private function _getfuturepickupdays($division)
	{
		$divisionname = $this->_divisionname($division);
		$return = '<optgroup  label="' .$divisionname . '">';
			$query = $this->db->query("SELECT distinct
			ff_pickupdates.pickupdate, ff_pickupdates.uid
			FROM (ff_pickupdates) 
			LEFT JOIN (ff_producttypes as pt) ON pt.bag = 'Y' and pt.id != 47
			LEFT JOIN ff_itemdays ON ff_itemdays.item = pt.id AND ff_itemdays.pickupday = ff_pickupdates.uid and ff_itemdays.lastorder is null
			WHERE `ff_pickupdates`.`division` = $division AND ff_pickupdates.pickupdate >= curdate() 
			ORDER BY ff_pickupdates.pickupdate desc");
		if ($query->num_rows() > 0)
		{
			foreach ($query->result() as $row)
			{
				$return .= '<option value="' . $row->uid .'">' . $row->pickupdate . "</option>\n";
			}
		} 
		$return .= '</optgroup>' ."\n";
		return $return;
	}
	
	
	
} // class Indkob 

/* End of file indkob.php */
/* Location: ./application/controllers/indkob.php */