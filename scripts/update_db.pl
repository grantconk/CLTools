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
$SIG{INT}=\&cleanUp;
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

# get links from db
print "\tGetting URLs from database...\n" if (defined $opt{'v'});
my $posts_href = getPosts();
foreach (keys %$posts_href) {
	verifyPost( $posts_href->{$_} );
}

cleanUp();

END {
	cleanUp();
}

# to prevent more than one running at same time
sub createLock
{
	system("date > .updating");
}

sub removeLock
{
	system("rm -f .updating");
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
	if ( -e ".updating" ) {
		die "Script is locked from previous run...";
	}
}

sub deletePost
{
	my($post_id) = $_[0];

    print "\t\tDeleting post: $post_id\n" if ( defined $opt{V} );

	my $sql = "DELETE FROM Posts WHERE id=$post_id";
    my $rows = $dbh->do( $sql );
	warn "Post $post_id was not found.\n" unless ($rows);
}

sub getPosts
{
	# get all posts
   	my $sql = "SELECT * FROM Posts";
	$sql .= " WHERE STR_TO_DATE(date_posted, '%b-%d-%y %k:%i:%s') > DATE_ADD(CURDATE(), INTERVAL $opt{m} MINUTE)" if ($opt{m});
	my $sth = $dbh->prepare($sql);
	$sth->execute || die $dbh->errstr ."\n$sql\n";
	my %posts;
	while (my $ref = $sth->fetchrow_hashref()) {
		$posts{ $ref->{id} } = $ref;
	}
	return \%posts;
}

sub cleanUpDB
{
   	#my $sql = "DELETE FROM Posts WHERE STR_TO_DATE(date_posted, '%b-%d-%y %k:%i:%s') < DATE_ADD(CURDATE(), INTERVAL 15 DAY)";
   	my $sql = "DELETE FROM Posts WHERE date_posted < DATE_SUB(CURDATE(), INTERVAL 15 DAY)";
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

sub verifyPost
{
	my $postref = $_[0];
	print "\tGetting post details for \"$postref->{title}\"...\n" if (defined $opt{V});

	# create url request
	my $ua = LWP::UserAgent->new;
	$ua->timeout(10);
	$ua->proxy('http', "http://$proxy_server");
	$ua->agent('Mozilla/4.0 (compatible; MSIE 5.5; Windows 98; AT&T CSM6.0)');
	my $response = $ua->get($postref->{url});
	my $content = $response->content if $response->is_success;
	if (!defined $content) {
		warn "WARNING: Couldn't get (postref->{url} $postref->{url}\n";
		warn "   ". getprint $postref->{url} ."\n***************************************************************\n";
	}
	# break up results into array
	my @lines = split('\n', $content);

    # loop through lines
	foreach (@lines) {
		# check for specific red flags
		if (/This posting has been deleted by its author/) {
				deletePost($postref->{id});
				print "\t\tPost has been deleted by its author.\n" if (defined $opt{V});
		} elsif (/<h2>This posting has been <(.+)> for removal/) {
				deletePost($postref->{id});
				print "\t\tPost has been flagged for removal.\n" if (defined $opt{V});
		}
	}	# end of for loop
}

sub init
{
	getopts('vVnm:hD:H:', \%opt ) or usage();
	usage() if $opt{h};

	# set defaults
	$opt{H} = 'localhost' unless defined $opt{H};
	$opt{D} = 'CLTools' unless defined $opt{D};
	$opt{n} = 10 unless defined $opt{n};
	$opt{m} = 10 unless defined $opt{m};
	$opt{v} = 1 if defined $opt{V};

	if (defined $opt{V}) {
		print "CLTools Populator...\n";
		print "\tSettings:\n";
		print "\t\thostname   : ". $opt{H} ."\n";
		print "\t\tdatabase   : ". $opt{D} ."\n";
		print "\t\tminutes    : ". $opt{m} ."\n";
		print "\t\tmax posts  : ". $opt{n} ."\n";
	}
}

sub usage
{
	print STDERR << "EOF";
 usage: $0 [-v|-V] [-H hostname] [-D database] [-h] [-n number] [-m minutes]

	-v			: some verbose output
	-V			: more verbose output
	-h			: show this help
	-H hostname	: hostname where external portal database is located
	-D database	: database where portal tables are located
	-m mins     : minutes since last run
	-n number   : max number of posts

 example: $0 -v -H localhost -D CLTools -m 30

EOF
	exit;
}

sub getProxyServer
{
	my @servers = (
		'193.37.152.154:3128',
		'67.69.254.247:80',
		#'222.68.206.11:80',
		'210.51.50.242:80',
		#'87.86.13.29:80',
		'67.69.254.252:80',
		#'200.174.85.195:3128',
		#'200.216.239.188:3128',
		'67.69.254.249:80',
		'203.160.001.112:80',
		#'148.233.159.57:80',
		'118.98.232.202:8080',
		'148.233.159.58:8080',
		'201.92.253.33:3128',
		#'202.54.61.99:8080',
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
		#'189.20.195.42:3128',
		'41.210.252.11:8080',
		'201.91.0.9:3128',
		'202.98.23.116:80',
		'121.11.87.171:80',
		'208.82.103.233:3128',
		#'202.98.23.114:80',
		#'201.12.62.4:3128',
		'87.244.203.134:3128',
		'222.242.188.170:8080',
		#'62.232.57.50:80',
		'200.170.221.10:80',
		'94.23.35.152:3128',
		'201.48.131.215:8080',
		'200.217.207.100:3128',
		#'66.79.171.227:80',
		'210.74.130.34:8080',
		'222.68.207.11:80',
		'67.69.254.254:80',
		#'83.218.161.211:8080',
		'200.139.78.100:3128',
		'91.148.153.5:3128',
		#'72.55.191.6:3128',
		'89.124.235.233:8080',
		'200.65.129.2:80',
		'114.127.246.36:8080',
		'67.18.208.82:80',
		'201.47.121.218:3128',
		#'203.160.1.121:80',
		#'222.124.201.180:80',
		'200.65.127.161:3128',
		'193.37.152.236:3128',
		#'148.233.239.23:80',
		'93.123.104.66:8080',
		'203.160.1.94:80',
		#'84.23.40.101:8080',
		#'118.175.255.10:80',
		'67.69.254.248:80',
		#'67.18.186.115:3128',
		#'67.69.254.246:80',
		'74.50.125.90:8080',
		'67.69.254.253:80',
		'89.124.235.236:8080',
		#'201.58.228.140:8080',
		#'206.19.212.169:80',
		'60.190.139.10:80',
		'216.17.103.46:80',
		#'61.172.249.94:80',
		'201.47.123.178:3128',
		'125.41.181.59:8080',
		'200.96.37.229:3128',
		#'189.18.78.60:8080',
		#'189.26.242.166:80',
		#'174.142.24.201:3128',
		#'203.160.001.103:80',
		'218.14.227.197:3128',
		'200.174.85.193:3128',
		#'202.125.40.36:3128',
		#'86.101.185.98:8080',
		#'200.178.161.11:80',
		#'201.47.170.59:3128',
		#'221.130.193.14:8080',
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
