<?php

	require_once("../config/db.php");
 require_once($_SERVER['DOCUMENT_ROOT'] . '/pro2/classes/AbstractProduct.php');
 
 class AbstractShoppingclass extends AbstractProductClass {
	//bid,auction variables 
 	protected $auction_start;
 	protected $auction_end;
 	protected $current_bid;
 	protected $previous_bid;
 	protected $current_user;
 	protected $previous_user;
 	
 	//flags
 	public $auction_status=999;
 	public $user_bid_status=999;
 	public $user_bid;
 	public $user_highest_bidder;
 	public $order_status;

 	//for insert into orders table
 	protected $order_id_hex;
 	protected $order_price;
 	protected $order_type;

 	
  
 	public function GetAuctionStart() {
 		return $this->auction_start;
 	}

 	public function GetAuctionEnd() {
 		return $this->auction_end;
 	}

 	public function GetCurrentHighestUser() {
 		return $this->current_user;
 	}
 	public function GetCurrentHighestBid() {
 		return $this->current_bid;

 	}


 	public function GetPreviousBid() {
 		return $this->previous_bid;
 	}

 	public function GetPreviousUser() {
 		return $this->previous_user;
 	}
    public function GetOrderCodeid() {
        return $order_id_hex;
    }


 	public function __construct(ProductDisplay $product) {

 		date_default_timezone_set('Asia/Kolkata');
 		$this->product_id = $product->product_id;
 		$this->product_bid_price = $product->product_bid_price;
        $this->product_max_price = $product->product_max_price;

 	
 		
 	}
 	public function ValidUser() {
 		//Inject the User class and its funcions into this class
 		if(isset($_SESSION['user_login_status']) && $_SESSION['user_login_status']==1)
 			return true;
 
 	}	
 	

    
    protected function  InsertBidsDb() {
    	$sql = "INSERT INTO bids(user_name,product_id,bid_amount) 
    			VALUES ('" . $_SESSION['user_name'] . "','" .$this->GetProductId() . "','" . $this->GetCurrentHighestBid() . "');";
    	$query_insert_bid = $this->db_connection->query($sql);
    	if($query_insert_bid)
    		return true;
    	else 
    		$this->errors[] = "Insert to bids table failed. Error code:". $this->db_connection->error . ".";
    }
    

    protected function InitAuctionMembers() {
    	$this->current_bid = $this->GetProductBidPrice();
    		$this->previous_bid = 0;
    		$this->current_user = null;
    		$this->previous_user = null;
    }

	protected function CheckBidAmount() {
		if($_POST['bid_amount']<$this->GetCurrentHighestBid())
			$this->messages[] = "You can't down bid. Your bid amount is lower than the current highest bid.Try again";
		elseif($_POST['bid_amount']==$this->GetCurrentHighestBid())
			$this->messages[] = "You are close!! Your current bid matches the current highest bid. Try again with a bigger bid.";
		/*
		A bit of work to be done in bit range. Just a prototype below 
		with bid increments being limited to 100.

		*/ 
		elseif($this->GetCurrentHighestBid() - $_POST['bid_amount']>100)
			$this->messages[] = "Sorry. Bid increments cannot exceed 100 at this moment. You can purchase the product at the maximum retail price instead.";
	else 
		return true;
	}
    

    protected function ValidBid() {
    	if(empty($_POST['bid_amount'])) 
			$this->errors[] = "You cannot leave an empty bid";
		else if (!preg_match('/^[0-9]{2,10}$/i', $_POST['bid_amount']))  
        			$this->errors[] = "Amount has to be an integer value.";
        else if(!$this->CheckBidAmount())
        	return false;
        else return true;
    }

    protected function ValidAuction() {
    	 if($this->setupDbConnection()==true)  {

    	 	$sql = "SELECT product_auction_start,product_auction_end from products_metadata where product_id='" . $this->GetProductId() . "';";

    	 	$query_check = $this->db_connection->query($sql);

    	 	if($query_check && $query_check->num_rows ==1) {
    	 		$obj = $query_check->fetch_object();
    	 		$this->auction_start = $obj->product_auction_start;
    	 		$this->auction_end = $obj->product_auction_end;
    	 		
    	 		if($this->CheckDateRange()==true) 
    	 			return true;
    	 		else return false;
    	 	}
    	 	else {
    	 		$this->errors[] = "The auction time was not set for this product!";
    	 	}

   		}
    }
    
    protected function hasUseralreadybid() {
    	if($this->setupDbConnection()==true)  {

    	 	$sql = "SELECT * from bids where user_name='" . $_SESSION['user_name'] . "' 
    	 			AND product_id='". $this->GetProductId() ."' ORDER BY bid_amount desc limit 1;";


    	 	$query_check = $this->db_connection->query($sql);

    	 	if($query_check && $query_check->num_rows ==1) {
    	 		$obj = $query_check->fetch_object();
    	 		$this->user_bid = $obj->bid_amount;
    	 		return true;
   		 	}
    		else false;
   		}
	}

	protected function isUserHighestBidder() {
		 if($this->setupDbConnection()==true)  {	
			$sql = "SELECT current_bid_user from auctions where product_id='" . $this->GetProductId(). "';";

    	 	$query_check = $this->db_connection->query($sql);

    	 	if($query_check && $query_check->num_rows ==1) {
    	 		$obj = $query_check->fetch_object();
    	 		if(strcmp($_SESSION['user_name'], $obj->current_bid_user)==0)
    	 			return true;
    	 	}
    	}
	}

    protected function orderStatus() {
    	 if($this->setupDbConnection()==true)  {

    	 	$sql = "SELECT product_auction_start,product_auction_end from products_metadata where product_id='" . $this->GetProductId() . "' AND product_auction_start IS NOT NULL;";

    	 	$query_check = $this->db_connection->query($sql);

    	 	if($query_check && $query_check->num_rows ==1) {
    	 		$obj = $query_check->fetch_object();
    	 		$this->auction_start = $obj->product_auction_start;
    	 		$this->auction_end = $obj->product_auction_end;
                
    	 		$start = strtotime($this->auction_start);
    			$end  = strtotime($this->auction_end);
    	 		$current_time =strtotime(date('Y-m-d H:i:s'));
    	 		if($current_time<$start && $current_time<$end)
    	 			$this->order_status=0;
    	 		elseif($current_time>$start && $current_time<$end)
    	 			$this->order_status=1;
    	 		elseif($current_time>$start && $current_time>$end)
    	 			$this->order_status=2;
    			
    			$this->SetorderStatusDb();
    	 	}
            else
                $this->order_status =0;

   		}
   	}

  protected function SetOrderStatusDb() {
     		if($this->setupDbConnection()==true)  {
     			$sql = "UPDATE products_metadata 
    					SET order_status='" . $this->order_status . "' 
    					WHERE product_id='". $this->GetProductId() . "';";
    			$query_update = $this->db_connection->query($sql);
    			if($query_update) 
    				return true;
    			else 
    				return false;

     		}
     }

  
    protected function CheckDateRange() {
    	$start = strtotime($this->auction_start);
    	$end  = strtotime($this->auction_end);
    	$current_time =strtotime(date('Y-m-d H:i:s'));
    	if($current_time<$start)
    		$this->messages[]= "The auction is set to begin at " . date('H:i:s',$start) . " on ". date('D')."day, " . date('d, M, Y',$start) . ". You are a bit early.";
    	else if($current_time>$end) 
    		$this->messages[]= "Oops. Time is up.!!";
    	else
    		return true;
    	
    }
    protected function GetAuctionStatus() {
    	if($this->setupDbConnection()==true)  {
    		$sql =  "SELECT * from auctions where product_id='" . $this->GetProductId() . "';";
    		$query_check = $this->db_connection->query($sql);
    		if($query_check && $query_check->num_rows ==1) 
    			return true;

   		}
   	}

    protected function GetAuctionStatusDb() {
        if($this->setupDbConnection()==true)  {
    		$sql =  "SELECT * from auctions where product_id='" . $this->GetProductId() . "';";
    		$query_check = $this->db_connection->query($sql);

    		if($query_check && $query_check->num_rows ==1) {
    	 		$obj = $query_check->fetch_object();
    		 	$this->current_bid = $obj->current_highest_bid;
    		 	$this->current_user = $obj->current_bid_user;
    	 		$this->previous_bid = $obj->previous_highest_bid;
    		 	$this->previous_user = $obj->previous_bid_user;
    		 	return true;
    		}

 			else 
 				return false;
 		}
 	}
//auction status  0 - not started
// 1 - started
// 2 - ended
//user bid status 0 - not bid
// 1 - has made a bid
// 2 - highest bidder
 	protected function ProductPageDisplay() {
       if($this->ValidUser()) {
       		$this->OrderStatus();
       		
       		if($this->order_status==0)
       			$this->InitAuctionMembers();
       
    		else if($this->order_status==1) {
       			if(!$this->GetAuctionStatusDb()) {
                	$this->InitAuctionMembers(); //FirstBidder
       			}
	       	
	      		if(!$this->hasUserAlreadyBid())
       				$this->user_bid_status=0;
       			else {
       				$this->user_bid_status =1;
       				if($this->isUserHighestBidder())
       					$this->user_highest_bidder =true;
       			}
       		}

     		else if($this->order_status==2) {
     			$this->GetAuctionStatusDb();
     			if( $this->hasUserAlreadyBid() && $this->isUserHighestBidder()) {
     				$this->user_highest_bidder =true;
     				$this->SetOrderDb();
     			}

     		}
     	}
     }

     protected function SetOrderDb() {
            $this->order_id_hex= $this->GenerateUniqueHash();
            
        if($this->GetOrderStatusDb()==2) { 
            $this->order_type = 0;
            $this->order_price = $this->GetCurrentHighestBiddb();
        }
        elseif($this->GetOrderStatusDb()==3) {
            $this->order_type =1;
            $this->order_price = $this->GetProductMaxPricedb();
        }
   

        if($this->setupDbConnection() == true) {
            $sql = "INSERT INTO orders(order_id_hex,user_name,product_id,order_type,price) VALUES('". $this->order_id_hex . "','" . $_SESSION['user_name'] . "','" . $this->GetProductId() . "','" . $this->order_type . "','" . $this->order_price."');"; 
            $query_insert = $this->db_connection->query($sql);
            if($query_insert) {
                return true;

            }

            else{ 
                $this->errors[] = "error," . $this->db_connection->error;

            }


        }
    }
   
   protected function GetCurrentHighestBiddb() {
        if($this->setupDbConnection()) {
            $sql = "SELECT current_highest_bid FROM auctions WHERE product_id='". $this->GetProductId() . "';";
            $query=$this->db_connection->query($sql);
            if($query) {
                $obj = $query->fetch_object();
                return $obj->current_highest_bid;
            }

        }
     }

     protected function GetProductMaxPricedb() {
        if($this->setupDbConnection()) {
            $sql = "SELECT product_max_price FROM products WHERE product_id='". $this->GetProductId() . "';";
            $query=$this->db_connection->query($sql);
            if($query) {
                $obj = $query->fetch_object();
                return $obj->product_max_price;
            }

        }
     }
     protected function GetOrderStatusDb() {
     		if($this->setupDbConnection()== true) {
     			$sql = "SELECT order_status FROM products_metadata
     					WHERE product_id = '". $this->GetProductId() ."';";
     			$query = $this->db_connection->query($sql);
     			if($query && $query->num_rows==1) {
     				$obj = $query->fetch_object();
     				$this->order_status = $obj->order_status;
     				return $this->order_status;
     			}
     			else return false;
     		}
     }

    
    public function GetOrderCode($product_id) {
    	if($this->setupDbConnection() == true) {

    		$sql = "SELECT order_id_hex from  orders 
    				WHERE product_id='". $product_id . "';";
    		$query= $this->db_connection->query($sql);
    		if($query&& $query->num_rows==1) {
    			$obj = $query->fetch_object();
    			return $obj->order_id_hex;

    		}
    	}
	}
}

      