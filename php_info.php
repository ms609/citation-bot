<?php
set_time_limit(240);
require_once 'html_headers.php';

ob_implicit_flush();
echo '<!DOCTYPE html><html lang="en" dir="ltr"><head><title>PHP Info</title></head><body><pre>';
pcntl_signal(SIGTERM, "sig_handler");
if (isset($_REQUEST['p']) && password_verify($_REQUEST['p'], '$2y$10$UOmZtkKs1X17vE/mmbfVgOiy0ZAkXnxa9UxFO97cFX8ioiJZpZ96S')) {
  /** @psalm-suppress ForbiddenCode */
  unset($_REQUEST['p'], $_GET['p'], $_POST['p'], $_SERVER['HTTP_X_ORIGINAL_URI'], $_SERVER['REQUEST_URI'], $_SERVER['QUERY_STRING']); // Anything that contains password string
  
  set_time_limit(240);
  echo "\n .nfs error file is:\n" . htmlspecialchars((string) shell_exec("(/usr/bin/tail -n 300 ../.nfs00000000050c0a6700000001 | /bin/grep -v '2022-05-18 '| /bin/grep -v '2022-05-17 '| /bin/grep -v '2022-05-16 '| /bin/grep -v '2022-05-15 '| /bin/grep -v '2022-05-14 ')  2>&1"), ENT_QUOTES);
  set_time_limit(240);
  echo "\n error file is: \n" . htmlspecialchars((string) shell_exec("(/usr/bin/tail -n 300 ../error.log )  2>&1"), ENT_QUOTES);
  set_time_limit(240);
  echo "\n attempt to delete .nfs error file: \n" . htmlspecialchars((string) shell_exec("(/bin/rm -rf ../.nfs000000000* )  2>&1"), ENT_QUOTES);
  set_time_limit(240);
  echo "\n\n" . htmlspecialchars((string) shell_exec("(/bin/ls -lahtr . .. )  2>&1"), ENT_QUOTES);
  set_time_limit(240);
  phpinfo(INFO_ALL);
 // set_time_limit(240);
 // echo "\n ZoteroWorked \n" . htmlspecialchars((string) shell_exec("(/bin/cat ./ZoteroWorked )  2>&1"), ENT_QUOTES);
 // set_time_limit(240);
 // echo "\n ZoteroFailed \n" . htmlspecialchars((string) shell_exec("(/bin/cat ./ZoteroFailed )  2>&1"), ENT_QUOTES);
  set_time_limit(240);
 // Since we do not use $PATH
 // echo "\n\n" . htmlspecialchars((string) shell_exec("(/bin/ls /bin /sbin /usr/bin /usr/sbin /usr/local/bin /usr/local/sbin )  2>&1"), ENT_QUOTES);
 //  /bin: bash cat chgrp chmod chown cp dash date dd df dir dmesg dnsdomainname domainname echo egrep false fgrep findmnt fuser grep gunzip gzexe gzip hostname journalctl less lessecho lessfile lesskey lesspipe ln login loginctl ls lsblk mkdir mknod mktemp more mount mountpoint mv nano networkctl nisdomainname pidof pwd rbash readlink rm rmdir rnano run-parts sed sh sleep stty su sync systemctl systemd systemd-ask-password systemd-escape systemd-inhibit systemd-machine-id-setup systemd-notify systemd-sysusers systemd-tmpfiles systemd-tty-ask-password-agent tar tempfile touch true umount uname uncompress vdir wdctl which ypdomainname zcat zcmp zdiff zegrep zfgrep zforce zgrep zless zmore znew
 //  /sbin: agetty badblocks blkdeactivate blkdiscard blkid blkzone blockdev cfdisk chcpu ctrlaltdel debugfs dmsetup dmstats dumpe2fs e2fsck e2image e2label e2mmpstatus e2undo fdisk findfs fsck fsck.cramfs fsck.ext2 fsck.ext3 fsck.ext4 fsck.minix fsfreeze fstab-decode fstrim getty halt hwclock init initctl installkernel isosize killall5 ldconfig logsave losetup mke2fs mkfs mkfs.bfs mkfs.cramfs mkfs.ext2 mkfs.ext3 mkfs.ext4 mkfs.minix mkhomedir_helper mkswap pam_tally pam_tally2 pivot_root poweroff raw reboot resize2fs runlevel runuser sfdisk shadowconfig shutdown start-stop-daemon sulogin swaplabel swapoff swapon switch_root telinit tune2fs unix_chkpwd unix_update wipefs zramctl
 //  /usr/bin: 2to3-2.7 [ addpart apt apt-cache apt-cdrom apt-config apt-get apt-key apt-mark arch awk b2sum base32 base64 basename bashbug bootctl busctl c_rehash captoinfo catchsegv chage chardet3 chardetect3 chattr chcon chfn choom chrt chsh cksum clear clear_console cmp comm compose corelist cpan cpan5.28-x86_64-linux-gnu csplit ctags ctags.emacs curl cut dbus-cleanup-sockets dbus-daemon dbus-monitor dbus-run-session dbus-send dbus-update-activation-environment dbus-uuidgen deb-systemd-helper deb-systemd-invoke debconf debconf-apt-progress debconf-communicate debconf-copydb debconf-escape debconf-set-selections debconf-show delpart deprecated-tomcat-starter dh_python2 diff diff3 dircolors dirname dpkg dpkg-deb dpkg-divert dpkg-maintscript-helper dpkg-query dpkg-split dpkg-statoverride dpkg-trigger du ebrowse ebrowse.emacs edit editor emacs emacs-nox emacsclient emacsclient.emacs enc2xs encguess env etags etags.emacs ex expand expiry expr factor faillog fallocate fc-cache fc-cat fc-conflist fc-list fc-match fc-pattern fc-query fc-scan fc-validate fincore find flock fmt fold gawk getconf getent getopt ginstall-info git git-receive-pack git-shell git-upload-archive git-upload-pack gpasswd gpgv groups gtk-update-icon-cache h2ph h2xs head helpztags hostid hostnamectl i386 iconv id infocmp infotocap install install-info instmodsh ionice ipcmk ipcrm ipcs ischroot join jq json_pp kernel-install killall last lastb lastlog lcf ldd less lessecho lessfile lesskey lesspipe libnetcfg link linux32 linux64 locale localectl localedef logger logname lsattr lscpu lsipc lslocks lslogins lsmem lsns mawk mcookie md5sum md5sum.textutils mesg mkfifo namei nawk newgrp nice nl nohup nproc nsenter numfmt od openssl pager partx passwd paste pathchk pdb pdb2 pdb2.7 pdb3 pdb3.7 peekfd perl perl5.28-x86_64-linux-gnu perl5.28.1 perlbug perldoc perlivp perlthanks phar phar.phar phar.phar7.3 phar7.3 php php-cgi php-cgi7.3 php7.3 pico piconv pinky pl2pm pldd pod2html pod2man pod2text pod2usage podchecker podselect pr print printenv printf prlimit prove prtstat pslog pstree pstree.x11 ptar ptardiff ptargrep ptx py3clean py3compile py3versions pyclean pycompile pydoc pydoc2 pydoc2.7 pydoc3 pydoc3.7 pygettext pygettext2 pygettext2.7 pygettext3 pygettext3.7 python python2 python2.7 python3 python3.7 python3.7m python3m pyversions realpath rename.ul renice reset resizepart resolvectl rev rgrep rsvg-convert rsvg-view-3 run-mailcap runcon rview rvim savelog script scriptreplay sdiff see select-editor sensible-browser sensible-editor sensible-pager seq setarch setpriv setsid setterm sg sha1sum sha224sum sha256sum sha384sum sha512sum shasum shred shuf sort splain split stat stdbuf sum systemd-analyze systemd-cat systemd-cgls systemd-cgtop systemd-delta systemd-detect-virt systemd-id128 systemd-mount systemd-path systemd-resolve systemd-run systemd-socket-activate systemd-stdio-bridge systemd-umount tabs tac tail taskset tee test tic timedatectl timeout toe touch tput tr truncate tset tsort tty tzselect ucf ucfq ucfr unexpand uniq unlink unshare update-alternatives update-mime-database users utmpdump vi view vim vim.basic vimdiff vimtutor wall wc webservice webservice-runner whereis which who whoami x86_64 xargs xsubpp xxd yes zdump zipdetails
 //  /usr/local/bin: composer
 //  /usr/local/sbin:
 //  /usr/sbin: add-shell addgroup adduser chgpasswd chmem chpasswd chroot cpgr cppw delgroup deluser dpkg-preconfigure dpkg-reconfigure e2freefrag e4crypt e4defrag fdformat filefrag groupadd groupdel groupmems groupmod grpck grpconv grpunconv iconvconfig invoke-rc.d ldattach lighttpd lighttpd-angel lighttpd-disable-mod lighttpd-enable-mod lighty-disable-mod lighty-enable-mod locale-gen mklost+found newusers nologin pam-auth-update pam_getenv pam_timestamp_check phpdismod phpenmod phpquery policy-rc.d pwck pwconv pwunconv readprofile remove-shell rmt rmt-tar rtcwake service tarcat tzconfig update-ca-certificates update-icon-caches update-info-dir update-locale update-mime update-passwd update-rc.d useradd userdel usermod validlocale vigr vipw zic
}

echo '</pre></body></html>';
?>
