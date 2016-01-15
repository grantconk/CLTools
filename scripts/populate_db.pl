#! /usr/bin/perl

# Populate the Craigs List database from the original sites

use strict;
use Getopt::Std;
use DBI;
use Date::Parse;
use Data::Dumper;
use LWP::Simple;
use LWP::UserAgent;
use List::Util 'shuffle';
$|=1;
$SIG{INT}=\&removeLock;
my %opt;

checkLock();
createLock();
init();

my $done = 0;
my $dsn = 'DBI:mysql:'. $opt{D} .':'. $opt{H};
my $username = 'cltools';
my $password = 'slootlc';
my $dbh = DBI->connect($dsn, $username, $password, {'RaiseError' => 1})
			|| die "CLTools database connection not made: $DBI::errstr";

# cean DB history
cleanUpDB();

# get a proxy server that wont be 403-Forbidden
my $proxy_server = getProxyServer();

# get hashes for easy lookup later
my $cats_href = getCategoriesByPath();
my $sites_href = getSitesByPath();
my $subsites_href = getSubSitesByPath();

# get regions tree
print "\nRetrieving regions...\n" if (defined $opt{'v'});
my @regions = getRegions();

if (defined $opt{v}) {
	print "\nFinal regions...\n";
    printRegions(\@regions, 3);
}

# get sites for regions
print "\nRetrieving sites...\n" if (defined $opt{'v'});
my %sites = getSites();

if (defined $opt{V}) {
	print "\nFinal sites...\n";
    foreach my $arry (values %sites) {
    	foreach my $href (@$arry) {
			print "\t$href->{name} \t: \t$href->{url} \n";
        }
    }
}

# get posts for all sites
print "\nSearching regions...\n" if (defined $opt{'v'});
searchRegions(\@regions);

cleanUp();

END {
	cleanUp();
}

# to prevent more than one running at same time
sub createLock
{
	system("date > .populating");
}

sub removeLock
{
	system("rm -f .populating");
}

# catch CTL+ALT+DEL and make sure lock file is removed
sub cleanUp
{
	removeLock();
	if (defined $opt{v} && !$done) {
		print "\nDone!\n";
		$done = 1;
	}
	exit;
}

sub checkLock
{
	if ( -e ".populating" ) {
		die "Script is locked from previous run...";
	}
}

sub addSubSite
{
	my($name, $path, $site_id) = $_[0];

    print "\t\tAdding subsite: $name ($path)\n" if ( defined $opt{V} );

	my $sql = "INSERT INTO SubSites (name, path, site_id, active) ";
    $sql .= "VALUES (";
    $sql .= $dbh->quote($name) .',';
    $sql .= $dbh->quote($path) .',';
    $sql .= "$site_id,";
    $sql .= "'on')";

    my $rows = $dbh->do( $sql );

	# get new record now
   	my $sql = "SELECT * FROM SubSites ";
    $sql .= "WHERE name=". $dbh->quote($name);
    $sql .= " AND path=". $dbh->quote($path);
    $sql .= " AND site_id=$site_id";
    $sql .= " AND active IS NOT null";
	my $sth = $dbh->prepare($sql);
	$sth->execute || die $dbh->errstr ."\n$sql\n";

    # return new hashref
    return $sth->fetchrow_hashref;
}

sub getSubSitesByPath
{
	# get all subsites
   	my $sql = "SELECT * FROM SubSites WHERE active IS NOT null";
	my $sth = $dbh->prepare($sql);
	$sth->execute || die $dbh->errstr ."\n$sql\n";
	my %subsites;

    print "\tCaching subsites...\n" if ( defined $opt{V} );

    # loop through categories
	while (my $ref = $sth->fetchrow_hashref()) {
		# get its parent site
   		$sql = "SELECT * FROM Sites WHERE id=$ref->{site_id}";
		my $sth2 = $dbh->prepare($sql);
		$sth2->execute || die $dbh->errstr ."\n$sql\n";
		my $ref2 = $sth2->fetchrow_hashref();
		$subsites{ $ref2->{path} . $ref->{path} } = $ref2;
    }
    return \%subsites;
}

sub getSitesByPath
{
	# get all sites
   	my $sql = "SELECT * FROM Sites WHERE active IS NOT null";
	my $sth = $dbh->prepare($sql);
	$sth->execute || die $dbh->errstr ."\n$sql\n";
	my %sites;

    print "\tCaching sites...\n" if ( defined $opt{V} );

    # loop through categories
	while (my $ref = $sth->fetchrow_hashref()) {
		$sites{ $ref->{url} } = $ref;
    }
    return \%sites;
}

sub cleanUpDB
{
   	my $sql = "DELETE FROM Posts WHERE STR_TO_DATE(date_posted, '%b-%d-%y %k:%i:%s') < ADDDATE(CURDATE(), INTERVAL 15 DAY)";
	my $sth = $dbh->prepare($sql);
	$sth->execute || die $dbh->errstr ."\n$sql\n";

	# check if table is acceptable size now
	$sql = "SELECT count(*) FROM Posts";
	$sth = $dbh->prepare($sql);
	$sth->execute || die $dbh->errstr ."\n$sql\n";
	my @row = $sth->fetchrow_array;
	if ($row[0] > 2000000) {
		die "Posts table is still too big!";
	} else {
		print "\tPosts table is acceptable now.\n" if ( defined $opt{V} );
	}
}

sub getCategoriesByPath
{
	# get all categories
   	my $sql = "SELECT * FROM Categories";
	my $sth = $dbh->prepare($sql);
	$sth->execute || die $dbh->errstr ."\n$sql\n";
	my %categories;

    print "\tCaching categories...\n" if ( defined $opt{V} );

    # loop through categories
	while (my $ref = $sth->fetchrow_hashref()) {
		$categories{ $ref->{path} } = $ref;
    }
    return \%categories;
}

sub savePost
{
	my $href = $_[0];

    if (ref($href) == 'HASH') {
        # check if post already exists
        my $sql = "SELECT id FROM Posts";
       	$sql .= " WHERE title=". $dbh->quote($href->{title});
        $sql .= " 	AND site_id=". $href->{site_id};
        $sql .= " 	AND flagged IS NULL";
		my $sth = $dbh->prepare($sql);
		$sth->execute || die $dbh->errstr ."\n$sql\n";

		if ( my @row = $sth->fetchrow_array() ) {
			# check if post was flagged to be removed or removed entirely (no date)
			if ( $href->{flagged} || !$href->{date_posted} ) {
				# remove the listing
				flagPost( $row[0] );
        		print "\t\t\t (removed): $href->{title}\n" if (defined $opt{V});
			} 
			# nope, it's probably a good post
			else {
        		print "\t\t\t (pass): $href->{title}\n" if (defined $opt{V});
			}
        } 
		# check if flagged already
		elsif ( !$href->{flagged} ) {
			print "\t\t\t Saving post: $href->{title}\n\t\t\t\t$href->{url}" if (defined $opt{V});

            $sql = "INSERT INTO Posts (title,url,price,site_id,category_id,date_posted,details,pic,img) ";
            $sql .= "VALUES (";
            $sql .= $dbh->quote("$href->{title}") .',';
            $sql .= $dbh->quote("$href->{url}") .',';
            $sql .= ($href->{price} ? $dbh->quote("$href->{price}") : "null") .',';
            $sql .= "$href->{site_id},";
            $sql .= ($href->{category_id} ? $href->{category_id} : "null") .',';
            #$sql .= ($href->{date_posted} ? "STR_TO_DATE('$href->{date_posted}', '%Y-%m-%d %h:%i%p')" : "null") .',';
            $sql .= ($href->{date_posted} ? "STR_TO_DATE('$href->{date_posted}', '%Y-%m-%d %h:%i%p')" : "NOW()") .',';
            $sql .= ($href->{details} ? $dbh->quote("$href->{details}") : "null") .',';
            $sql .= ($href->{pic} ? "'on'" : "null") .',';
            $sql .= ($href->{img} ? "'on'" : "null");
            $sql .= ")";
            my $rows = $dbh->do( $sql );
		}
		else {
			print "\t\t\t Bipassing flagged post: $href->{title}\n\t\t\t\t$href->{url}" if (defined $opt{V});
		}
    }
}

sub flagPost
{
	my $id = $_[0];

	my $sql = "UPDATE Posts SET flagged='on' WHERE id=$id";
	$dbh->do( $sql );
}

sub getPostDetails
{
	my $postref = $_[0];
    my $site = ();
	my($body_tag, $parse_details, $read_blurbs, $read_date_posted);
	print "\n\t\t\tGetting post details for \"$postref->{title}\"...\n" if (defined $opt{V});

	# create url request
	my $ua = LWP::UserAgent->new;
	$ua->timeout(10);
	#$ua->proxy('http','http://63.208.180.6:3128');
	$ua->proxy('http', "http://$proxy_server");
	$ua->agent('Mozilla/4.0 (compatible; MSIE 5.5; Windows 98; AT&T CSM6.0)');
	my $response = $ua->get($postref->{url});
	my $content = $response->content if $response->is_success;
	#my $content = get($postref->{url});
	if (!defined $content) {
		warn "WARNING: Couldn't get (postref->{url} $postref->{url}\n";
		warn "   ". getprint $postref->{url} ."\n***************************************************************\n";
		return $postref;
	}
	# break up results into array
	my @lines = split('\n', $content);

    # loop through lines
	LINE: for (my $x=0; $x< scalar @lines; $x++) {

		# check if we have the body tag yet
		if ( !$body_tag ) {
			# check if this the <body> tag
			#if ( $lines[$x] =~ /<body/ ) {
			if ( $lines[$x] =~ />email this posting to a friend</ ) {
				# record we've got it
				$body_tag = 1;
			}
			# now just goto the next line
			next LINE;
		}
		# check if we have the site info yet which comes directly after the "email this posting" line
		# but this value will probably already be set
		elsif ( $body_tag && !$postref->{site_id} ) {

			# zip to pictures once we hit the "It is not ok to contact this poster" line"
			# <ul style="margin-left:0px; padding-left:3px; list-style:none; font-size: smaller">

			# a couplelines after <body> should be it
            # <a href="http://sfbay.craigslist.org">s.f. bayarea craigslist</a>
			if ( $lines[$x] =~ /<a href="([^"]*)">([^<]+) craigslist<\/a>/ ) {
                # record site id
                $site = $sites_href->{$1};
				$postref->{site_id} = $site->{id};

                next LINE;
			}
        }
        # check if we have category info yet which comes directly after site info
        elsif ( $body_tag && !$postref->{category_id} ) {
			# extract link info
            # 	&gt; <a href="/eby/">east bay</a> &gt;  <a href="/eby/fur/">furniture</a>
            $lines[$x] =~ /&gt;  <a href\="\/([^"]*)">([^<]+)<\/a>[\s*&gt;\s*<a href="\/[^"]*]?/;
			my $cat_path = $1;
			my $cat_name = $2;

            # check if we have subsite info
            if ( $3 && $4 ) {
            	# record subsite id
				$postref->{subsite_id} = $subsites_href->{ $site->{path} . $3 }->{id};

                # record category id now
				$postref->{category_id} = $cats_href->{ $4 }->{id} unless ($postref->{category_id});
            } elsif ( $cat_path && $cat_name ) {
            	# verify we have this category id in database
                if ( !$cats_href->{ $cat_path }->{id} ) {
					# extract the subsite from the path if its prepended
					$cat_path =~ /[a-z]+\/([a-z]+\/)/;
					
					# set new category path
					$cat_path = $1;

           			# verify we have this category id in database
               		if ( !$cats_href->{ $cat_path }->{id} ) {
						print "** WARNING ** No category id for $cat_path ($cat_name)\n";
					}
                }
            	# record category id only
				$postref->{category_id} = $cats_href->{ $cat_path }->{id} unless ($postref->{category_id});
            }
			# continue to next line
            next LINE;
		}
		# check if we have the posting date yet, comes after Replyto under horizontal bar 
        elsif ( $body_tag && !$postref->{date_posted} ) {
			# extract date 
            # Date: 2009-01-23,  7:05PM EST<br>
            $lines[$x] =~ /^Date: (.*),(\s+)(.*)<br>?/;
			my $date = $1;
			my $time = $3;

            # check if we have date and time
            if ($date && $time) {
				$postref->{date_posted} = "$date $time";
			}
		}
		# check if we should be parsing the details
		elsif ( $body_tag && $parse_details ) {
			# check if this is the end of details
			if ( $lines[$x] =~ /^PostingID: / ) {
				# we're done parsing the details now
				$parse_details = '';
			} else {
				# append to details value
				$postref->{details} .= $lines[$x];
			}

			# continue to next line
            next LINE;
		} elsif ( $body_tag ) {
			# the title H2 tag should be directly after the scams line
			#if ( $lines[$x] =~ /scams\.html\">More info<\/a><\/div>$/ ) {
			if ( $lines[$x] =~ /^<div id=\"userbody\">/ ) {
				# start with a DIV tag since we're omitting it here
				$postref->{details} = '<div id="userbody">';
				$parse_details = 1;
			}
		}
	}	# end of for loop

    return $postref;
}

sub getPostListing
{
	my $line = $_[0];
	chomp($line);
    # check if this looks like a post listing
    # /^<p><a href=\"([^<]+)\">([^<]+)<\/a>(.*)/
    #if ( $_[0] =~ /^<p><a href=\"([^<]+)\">([^<]+)<\/a>(.*)$/ ) {
    if ( $line =~ /^<p><a href=\"([^<]+)\">([^<]+)<\/a>(.*)$/ ) {
    	my %post;
        $post{url} = $1;
        my($title,$price) = split(' - \$', $2);
		$price =~ s/ -//;
        $post{title} = $title;
        $post{price} = $price;
        $post{pic} = 'yes' if ($3 =~ / pic<\/span>/);
        $post{img} = 'yes' if ($3 =~ / img<\/span>/);
		if ($line =~ m| &lt;&lt;<i><a href=\"/([^\"]+)\">([^<]+)</a></i></p>|) {
        	$post{category_path} = $1;
			$post{category_id} = $cats_href->{ $1 }->{id};
		}
   		return \%post;
    }
}

sub parseSearchResults
{
	my($site_id, $url, @lines) = @_;
    my $post_count = 0;
	print "\t\tParsing search results...\n" if (defined $opt{v});

    # loop through lines
	for (my $x=0; $x< scalar @lines; $x++) {
    	# check for post listing
        if ( my $postref = getPostListing( $lines[$x] ) ) {
        	# check if we've hit our max count yet
            if ( $post_count >= $opt{n} ) {
            	print "\t\tNOTE: We hit our maximumn number of posts: $opt{n}\n" if (defined $opt{V});
           		return;
            }

            # get post content
            $postref->{site_id} = $site_id;
            #####$postref->{url} = $url . $postref->{url};
            $postref = getPostDetails($postref);

            # save post content to our own database
            savePost($postref);

            # keep track of post count
            $post_count++;
        }
    }
}

sub doSearch
{
	my $sites = $_[0];

    # verify if we have sites to search
	if ( scalar @{$sites} ) {
    	SITE: foreach my $href ( @${sites} ) {
  			my $url = $href->{url} . $href->{search_path};
        	print "\tSearching: $url\n" if (defined $opt{V});

            # create search url
			my $ua = LWP::UserAgent->new;
			$ua->timeout(10);
			$ua->proxy('http', "http://$proxy_server");
			$ua->agent('Mozilla/4.0 (compatible; MSIE 5.5; Windows 98; AT&T CSM6.0)');
			my $response = $ua->get($url);
			my $content = $response->content if ($response->is_success);
			#my $content = get($url);
			if (!defined $content) {
            	warn "WARNING: Couldn't get (url) $url\n";
				$response = $ua->get($url);
				if ($response->is_success) {
            		warn "\tbut there was a response\n";
				}
				##warn "   ". getprint $url ."\n\n";
                next SITE;
			}
            # break up results into array
            my @arry = split('\n', $content);

            # send to parse out results and retrieve post data
            parseSearchResults( $href->{id}, $href->{url}, @arry );
        }
    }
}

sub searchRegions
{
	my $regs = $_[0];
	my $depth = $_[1];

    # print out regions and children recursively
	for my $href ( @$regs ) {
    	# check if this region has sites
        if ( defined $sites{$href->{id}} ) {
			print " "x$depth . $href->{name} ." (". scalar @{$href->{subregs}} ." children) => ". scalar @{$sites{$href->{id}}} ."\n" if (defined $opt{V});
            doSearch( $sites{$href->{id}} );
        }
		searchRegions($href->{subregs}, $depth+3);
	}
}

#
# Load sites for each region, recursively
#
sub getSites
{
	# get sites for region
   	my $sql = "SELECT * FROM Sites WHERE active IS NOT null ORDER BY region_id,name";
	my $sth = $dbh->prepare($sql);
	$sth->execute || die $dbh->errstr ."\n$sql\n";
	my %sites;

    # loop through sites
	SITE: while (my $ref = $sth->fetchrow_hashref()) {
		my $region_id = $ref->{region_id};

        # check if we are to do this site
		#next SITE unless ($region_id);

        # check if we have any existing sites for this region
        if ( $region_id && $sites{$region_id} ) {
            # push new site into existing list
            push(@{$sites{$region_id}}, $ref);
        } elsif ($region_id) {
	        # create array of sites for this region
            $sites{$region_id} = [$ref];
        } else {
        	print "Error: No region_id found.\n";
        }
		print "\t". $ref->{name} ."\n" if (defined $opt{V});
	}
    # return this hash of arrays of sites
	return %sites;
}

sub getRegions
{
	my $parent_id = $_[0];
	my $sql = "SELECT * FROM Regions WHERE parent_id";
	$sql .= ($parent_id ? "=$parent_id " : ' IS null ');
	$sql .= " AND active='on'";
	$sql .= " ORDER BY name";
	my $sth = $dbh->prepare($sql);
	$sth->execute || die $dbh->errstr ."\n$sql\n";
	my @regs;

    # loop through regions for this parent
	while (my $ref = $sth->fetchrow_hashref()) {
		my $hash = $ref;

        # get children regions, continues recursively
		$hash->{subregs} = [ getRegions($hash->{id}) ];

        # push into array of regions
		push(@regs, $hash);
		print "\t$hash->{id} = $hash->{name} : ". scalar @{$hash->{subregs}} ." children\n" if (defined $opt{V});
	}

    # return this array of regions
	return (@regs);
}

sub printRegions ()
{
	my $regs = $_[0];
	my $depth = $_[1];

    # print out regions and children recursively
	for my $href ( @$regs ) {
		print " "x$depth . $href->{name} ." (". scalar @{$href->{subregs}} .")\n";
		printRegions($href->{subregs}, $depth+3);
	}
}

sub init
{
	getopts('vVn:hD:H:', \%opt ) or usage();
	usage() if $opt{h};

	# set defaults
	$opt{H} = 'localhost' unless defined $opt{H};
	$opt{D} = 'CLTools' unless defined $opt{D};
	$opt{n} = 10 unless defined $opt{n};
	$opt{v} = 1 if defined $opt{V};

	if (defined $opt{V}) {
		print "CLTools Populator...\n";
		print "\tSettings:\n";
		print "\t\thostname   : ". $opt{H} ."\n";
		print "\t\tdatabase   : ". $opt{D} ."\n";
		print "\t\tmax posts  : ". $opt{n} ."\n";
	}
}

sub usage
{
	print STDERR << "EOF";
 usage: $0 [-v|-V] [-H hostname] [-D database] [-h] [-n number]

	-v			: some verbose output
	-V			: more verbose output
	-h			: show this help
	-H hostname	: hostname where external portal database is located
	-D database	: database where portal tables are located
	-n number   : maximum number of posts to request [default=10]

 example: $0 -v -H localhost -D CLTools -n 20

EOF
	exit;
}

sub getProxyServer
{
	my @servers = (
		'193.37.152.154:3128',
		'67.69.254.247:80',
		'222.68.206.11:80',
		'210.51.50.242:80',
		'87.86.13.29:80',
		'67.69.254.252:80',
		'200.174.85.195:3128',
		'200.216.239.188:3128',
		'67.69.254.249:80',
		'203.160.001.112:80',
		'148.233.159.57:80',
		'118.98.232.202:8080',
		'148.233.159.58:8080',
		'201.92.253.33:3128',
		'202.54.61.99:8080',
		'67.69.254.250:80',
		'83.2.83.44:8080',
		'60.12.226.18:80',
		'193.37.152.216:3128',
		'61.172.244.108:80',
		'201.82.15.128:8080',
		'119.70.40.101:8080',
		'211.90.137.93:8000',
		'203.162.183.222:80',
		'75.101.195.215:80',
		'189.20.195.42:3128',
		'41.210.252.11:8080',
		'201.91.0.9:3128',
		'202.98.23.116:80',
		'121.11.87.171:80',
		'208.82.103.233:3128',
		'202.98.23.114:80',
		'201.12.62.4:3128',
		'87.244.203.134:3128',
		'222.242.188.170:8080',
		'62.232.57.50:80',
		'200.170.221.10:80',
		'94.23.35.152:3128',
		'201.48.131.215:8080',
		'200.217.207.100:3128',
		'66.79.171.227:80',
		'210.74.130.34:8080',
		'222.68.207.11:80',
		'67.69.254.254:80',
		'83.218.161.211:8080',
		'200.139.78.100:3128',
		'91.148.153.5:3128',
		'72.55.191.6:3128',
		'89.124.235.233:8080',
		'200.65.129.2:80',
		'114.127.246.36:8080',
		'67.18.208.82:80',
		'201.47.121.218:3128',
		'203.160.1.121:80',
		'222.124.201.180:80',
		'200.65.127.161:3128',
		'193.37.152.236:3128',
		'148.233.239.23:80',
		'93.123.104.66:8080',
		'203.160.1.94:80',
		'84.23.40.101:8080',
		'118.175.255.10:80',
		'67.69.254.248:80',
		'67.18.186.115:3128',
		'67.69.254.246:80',
		'74.50.125.90:8080',
		'67.69.254.253:80',
		'89.124.235.236:8080',
		'201.58.228.140:8080',
		'206.19.212.169:80',
		'60.190.139.10:80',
		'216.17.103.46:80',
		'61.172.249.94:80',
		'201.47.123.178:3128',
		'125.41.181.59:8080',	
		'200.96.37.229:3128',
		'189.18.78.60:8080',
		'189.26.242.166:80',
		'174.142.24.201:3128',
		'203.160.001.103:80',
		'218.14.227.197:3128',
		'200.174.85.193:3128',
		'202.125.40.36:3128',
		'86.101.185.98:8080',
		'200.178.161.11:80',
		'201.47.170.59:3128',
		'221.130.193.14:8080',
		'114.30.47.10:80',
		'218.25.92.59:80',
		'69.30.227.98:3128',
	);
	my $ua_tmp = LWP::UserAgent->new;
	$ua_tmp->timeout(10);
	$ua_tmp->agent('Mozilla/4.0 (compatible; MSIE 5.5; Windows 98; AT&T CSM6.0)');
	foreach my $server ( shuffle(@servers) ) {
		$ua_tmp->proxy('http', "http://$server");
		# test if we get 403-Forbidden using this proxy server
		print "\t\tProxy Test: http://$server ... " if (defined $opt{V});
		my $response = $ua_tmp->get('http://sfbay.craigslist.org/sss/');
		if ($response->is_success) {
			print "SUCCESS!\n" if (defined $opt{V});
			return $server;
		}
		print "Failed\n" if (defined $opt{V});
	}
	print "ERROR: No proxy servers were able to get through.\n";
	exit;
}

sub test
{
	my(@arry,%hash);
	my $id = 777;
	my $arryref = \@arry;
	my %hash1 = (first=>'Grant',last=>'Conklin');
	my %hash2 = (first=>'John',last=>'Doe');
	my %hash3 = (first=>'Jane',last=>'Doed');
	my $hashref1 = \%hash1;
	my $hashref2 = \%hash2;
	my $hashref3 = \%hash3;
	push(@{$arryref}, $hashref1);
	push(@{$arryref}, $hashref2);
	#$hash{$id} = \@arry;
	$hash{$id} = $arryref;
	push(@{$hash{$id}}, $hashref3);
	print "\n\%hash.........\n". Dumper(%hash);
	exit;
}
