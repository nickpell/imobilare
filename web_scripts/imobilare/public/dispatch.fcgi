#!/usr/bin/ruby
#
# You may specify the path to the FastCGI crash log (a log of unhandled
# exceptions which forced the FastCGI instance to exit, great for debugging)
# and the number of requests to process before running garbage collection.
#
# By default, the FastCGI crash log is RAILS_ROOT/log/fastcgi.crash.log
# and the GC period is nil (turned off).  A reasonable number of requests
# could range from 10-100 depending on the memory footprint of your app.
#
# Example:
#   # Default log path, normal GC behavior.
#   RailsFCGIHandler.process!
#
#   # Default log path, 50 requests between GC.
#   RailsFCGIHandler.process! nil, 50
#
#   # Custom log path, normal GC behavior.
#   RailsFCGIHandler.process! '/var/log/myapp_fcgi_crash.log'
#
require File.dirname(__FILE__) + "/../config/environment"
require 'fcgi_handler'

## Commented out by scripts.mit.edu autoinstaller
## RailsFCGIHandler.process!

## Added by scripts.mit.edu autoinstaller to reload when app code changes
Thread.abort_on_exception = true

t1 = Thread.new do
   RailsFCGIHandler.process!
end

t2 = Thread.new do
   # List of directories to watch for changes before reload.
   # You may want to also watch public or vendor, depending on your needs.
   Thread.current[:watched_dirs] = ['app', 'config', 'db', 'lib']

   # List of specific files to watch for changes.
   Thread.current[:watched_files] = ['public/dispatch.fcgi',
				     'public/.htaccess']
   # Sample filter: /(.rb|.erb)$/.  Default filter: watch all files
   Thread.current[:watched_extensions] = //
   # Iterations since last reload
   Thread.current[:iterations] = 0

   def modified(file)
     begin
       mtime = File.stat(file).mtime
     rescue
       false
     else
       if Thread.current[:iterations] == 0
         Thread.current[:modifications][file] = mtime
       end
       Thread.current[:modifications][file] != mtime
     end
   end

   # Don't symlink yourself into a loop.  Please.  Things will still work
   # (Linux limits your symlink depth) but you will be sad
   def modified_dir(dir)
     Dir.new(dir).each do |file|
       absfile = File.join(dir, file)
       if FileTest.directory? absfile
         next if file == '.' or file == '..'
         return true if modified_dir(absfile)
       else
         return true if Thread.current[:watched_extensions] =~ absfile &&
	   modified(absfile)
       end
     end
     false
   end

   def reload
     Thread.current[:modifications] = {}
     Thread.current[:iterations] = 0
     # This is a kludge, but at the same time it works.
     # Will kill the current FCGI process so that it is reloaded
     # at next request.
     raise RuntimeError
   end

   Thread.current[:modifications] = {}
   # Wait until the modify time changes, then reload.
   while true
     dir_modified = Thread.current[:watched_dirs].inject(false) {|z, dir| z || modified_dir(File.join(File.dirname(__FILE__), '..', dir))}
     file_modified = Thread.current[:watched_files].inject(false) {|z, file| z || modified(File.join(File.dirname(__FILE__), '..', file))}
     reload if dir_modified || file_modified
     Thread.current[:iterations] += 1
     sleep 1
   end
end

t1.join
t2.join
## End of scripts.mit.edu autoinstaller additions
