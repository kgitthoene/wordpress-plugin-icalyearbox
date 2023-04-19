#!/usr/bin/ruby
# #region[rgba(0, 255, 0, 0.05)] SOURCE-STUB
#
#
#----------
# Set some global variables.
#
$g_me = $PROGRAM_NAME.to_s
$g_myname = File.basename($g_me)
$g_mydir = File.dirname($g_me)
#
#
#----------
# Get setup token.
#
begin
  b_setup = false
  ARGV.each { |arg| if arg == '++setup' then b_setup = true; break; end }
  if b_setup
    require 'open3'
    captured_stdout = ''
    captured_stderr = ''
    exit_status = Open3.popen3('sudo', '-H', '/bin/sh', '-i') do |stdin, stdout, stderr, wait_thr|
      _pid = wait_thr.pid # pid of the started process.
      stdin.puts <<~EOF
        #!/bin/sh
        ##apt -y install ruby-libxml
        ##gem install rspreadsheet
        echo HI
        exit 0
      EOF
      stdin.close
      captured_stdout = stdout.read
      captured_stderr = stderr.read
      wait_thr.value # Process::Status object returned.
    end
    if exit_status.success?
      warn 'SETUP -- OK.'
    else
      warn 'ERROR -- Setup failed!'
      warn captured_stdout
      warn captured_stderr
    end
    exit exit_status.success? ? 0 : 1
  end
end
#
#
#----------
# Load some files / modules.
#
#
#
#----------
# Load some files / modules.
(%w[optparse pty open3 pp yaml fileutils tempfile highline/import date time tzinfo tzinfo/data csv] + []).each do |s_module_name|
  begin
    require s_module_name
  rescue LoadError => e
    warn "[MODULE=\"#{s_module_name}\"] ERROR -- #{e}"
    exit 1
  end
end
#
#
#----------
# Define some essential functions.
#
class It
  @@options = { info: true, error: true, warn: true, debug: false, verbose: false, quiet: false }
  @@me = $PROGRAM_NAME.to_s
  @@myname = File.basename(@@me)
  @@mydir = File.dirname(@@me)

  class << self
    def info(msg_p)
      msg_p.to_s.each_line { |l| out "#{HighLine.color('I', :cyan, :bold)} #{@@myname}: #{l}" } if @@options[:info] && !@@options[:quiet]
    end

    def error(msg_p)
      msg_p.to_s.each_line { |l| out "#{HighLine.color('E', :red, :bold)} #{@@myname}: #{l}" } if @@options[:error] && !@@options[:quiet]
    end

    def warn(msg_p)
      msg_p.to_s.each_line { |l| out "#{HighLine.color('I', :yellow, :bold)} #{@@myname}: #{l}" } if @@options[:warn] && !@@options[:quiet]
    end

    def debug(msg_p)
      msg_p.to_s.each_line { |l| out "#{HighLine.color('D', :blue, :bold)} #{@@myname}: #{l}" } if @@options[:debug] && !@@options[:quiet]
    end

    def verbose(msg_p)
      msg_p.to_s.each_line { |l| out "#{HighLine.color('V', :blue, :bold)} #{@@myname}: #{l}" } if @@options[:verbose] && !@@options[:quiet]
    end

    def out(msg_p)
      Kernel.warn msg_p.to_s
    end

    def setup(opts = {})
      opts.each_pair do |k, v|
        @@options[k] = v if k.instance_of?(Symbol)
      end
    end

    # FROM:https://stackoverflow.com/questions/2108727/which-in-ruby-checking-if-program-exists-in-path-from-ruby
    # Cross-platform way of finding an executable in the $PATH.
    #
    #   which('ruby') #=> /usr/bin/ruby
    def which(cmd)
      exts = ENV['PATHEXT'] ? ENV['PATHEXT'].split(';') : ['']
      ENV['PATH'].split(File::PATH_SEPARATOR).each do |path|
        exts.each do |ext|
          exe = File.join(path, "#{cmd}#{ext}")
          return exe if File.executable?(exe) && !File.directory?(exe)
        end
      end
      nil
    end

    def check_tools(a_tools_p)
      a_tools_p.each do |tool|
        next unless which(tool).nil?
        error "Cannot find needed external tool '#{tool}'!"
        exit 1
      end
      true
    end

    def yesno(prompt = 'Continue?', default = true)
      a = ''
      s = default ? '[Y/n]' : '[y/N]'
      d = default ? 'y' : 'n'
      until %w[y n].include? a
        a = ask("#{prompt} #{s} ") { |q| q.limit = 1; q.case = :downcase }
        a = d if a.empty?
      end
      a == 'y'
    end

    def deltat2dhms(deltat_p)
      seconds = deltat_p * 24 * 3600
      days = (seconds / 3600 / 24).floor
      seconds -= days * 3600 * 24
      hours = (seconds / 3600).floor
      seconds -= hours * 3600
      minutes = (seconds / 60).floor
      seconds -= minutes * 60
      [days, hours, minutes, seconds]
    end # deltat2dhms

    # rc, stdout, stderr = It.execute_command(...)
    # rc, stdout, stderr = It.execute_command(..., :stdin = [String | File])
    def execute_command(*args)
      options = args.pop if args[-1].is_a?(Hash)
      #It.info("execute_command: OPTIONS=#{options.inspect}")
      captured_stdout = ''
      captured_stderr = ''
      exit_status = Open3.popen3(*args) do |stdin, stdout, stderr, wait_thr|
        unless options.nil? || options[:stdin].nil?
          if options[:stdin].is_a?(String)
            File.open(options[:stdin]) { |f| stdin.write_nonblock(f.read) }
          else
            stdin.write_nonblock(options[:stdin].read)
          end
        end
        _pid = wait_thr.pid # pid of the started process.
        stdin.close
        captured_stdout = stdout.read
        captured_stderr = stderr.read
        wait_thr.value # Process::Status object returned.
      end
      # exit_status.success? => true, false
      # exit_status.exitstatus => Fixnum
      [exit_status.exitstatus, captured_stdout, captured_stderr]
    end # execute_command

    def optparse
      options = {}
      options[:stop_after_optionparser] = false
      options[:debug] = false
      options[:write_sample_config_file] = false
      options[:verbose] = false
      options[:quiet] = false
      options[:config_default] = 'config.yaml'
      options[:config] = nil
      optparse = OptionParser.new do |opts|
        opts.banner = "Usage: #{$g_myname} [-dvC] [-c CONFIG] -w WATERMARK [...]"
        opts.on('-d', '--debug', 'Enable debugging.') do |_dummy|
          options[:debug] = true
        end
        opts.on('-v', '--verbose', 'Talk more.') do |_dummy|
          options[:verbose] = true
        end
        opts.on('-q', '--quiet', 'Talk nothing.') do |_dummy|
          options[:quiet] = true
        end
        opts.on('-C', '--write-sample-config-file', 'Writes a sample config file.') do |_dummy|
          options[:write_sample_config_file] = true
        end
        opts.on('-c CONFIG', '--config CONFIG', String, "Config file. (Default: '#{options[:config_default]}')") do |_data|
          options[:config] = _data
        end
        # opts.on('-g GROUPS', '--groups GROUPS', Integer, /[0-9]*/, "(Default: #{options[:nr_groups]}) Number of groups (>= 1).") do |num|
        #  options[:nr_groups] = num.to_i
        # end
        # opts.on('-t TEMPLATE', '--template TEMPLATE', String, "(Default: '#{options[:html_template]}') HTML template file.") do |file|
        #  options[:html_template] = file
        # end
        opts.on_tail('-h', '-?', '--help', 'Display this screen.') do
          puts opts
          # msg =<<EOF
          # Description
          #  Send a wakeup to all MAC addresses.
          # EOF
          # puts msg
          options[:stop_after_optionparser] = true
        end
      end
      begin
        optparse.parse!(ARGV)
        exit 0 if options[:stop_after_optionparser]
        # raise(OptionParser::MissingArgument, "No MAC address given!") if ARGV.empty?
        # OptionParser::AmbiguousArgument
        # OptionParser::AmbiguousOption
        # OptionParser::InvalidArgument
        # OptionParser::InvalidOption
        # OptionParser::MissingArgument
        # OptionParser::NeedlessArgument
        # OptionParser::ParseError
        # raise(OptionParser::ParseError, "GROUPS must be >= 1!") if options[:nr_groups] and options[:nr_groups] < 1
        # raise(OptionParser::ParseError, "GROUP-SIZE must be >= 2!") if options[:group_size] and options[:group_size] < 2
        raise(OptionParser::InvalidArgument, 'Config file not found!') if options[:config] && !File.exist?(options[:config])
      rescue OptionParser::ParseError => e
        It.error e.to_s
        optparse.parse('-h')
        exit 1
      end
      options[:config] = options[:config_default] if options[:config].nil?
      options.delete(:stop_after_optionparser)
      # Write sample config file.
      if options[:write_sample_config_file]
        cfg = options[:config]
        sample_config = "---\nconfig: #{cfg}\n"
        if File.exist?(cfg)
          It.error "Config file '#{cfg}' exists!"
          It.info 'A sample config file is displyed here:'
          $stdout << sample_config
        else
          File.open(cfg, 'w') { |of| of << sample_config }
          It.info "Config was written to file '#{cfg}'!"
        end
      end
      setup({ debug: options[:debug], verbose: options[:verbose], quiet: options[:quiet] })
      options
    end # optparse

    def config(filename = nil, convert_strings_to_symbols_p = false)
      config = nil
      # Read config file.
      #It.info("It.config: FILENAME=#{filename.inspect}")
      if !filename.nil? && File.exist?(filename)
        config = begin
                   YAML.safe_load(File.open(filename))
                 rescue StandardError => e
                   It.error(e)
                   #It.pp_exception(e)
                   nil
                 end
        # if $g_options[:start_year].nil? or $g_options[:uri].nil?
        #  It.error "Invalid config file '#{$g_options[:config]}'!"
        #  exit 1
        # end
      end
      # If possible convert names to symbols.
      if convert_strings_to_symbols_p && config.is_a?(Hash)
        nc = {}
        config.each_pair do |k, v|
          if k.is_a?(String)
            nc[k.to_sym] = v
          else
            nc[k] = v
          end
        end
        config = nc
      end
      #It.info("It.config: CONFIG=#{config.inspect}")
      config
    end # config

    class AssertionError < RuntimeError
    end

    def assert(*args)
      msg = nil
      if args.length >= 2
        msg = args.pop if args.last.is_a?(String)
      end
      if block_given?
        unless yield(args)
          backtrace = []
          caller_locations.each { |l| backtrace << l.to_s }
          e = AssertionError.new(msg)
          e.set_backtrace(backtrace)
          raise e
        end
      else
        args.each do |arg|
          next if arg
          # error "AssertionError " + (msg ? "-- #{msg} " : "") + "/ BACKTRACE="
          backtrace = []
          caller_locations.drop(2).each { |l| backtrace << l.to_s }
          e = AssertionError.new(msg)
          e.set_backtrace(backtrace)
          raise e
        end
      end
    end # assert

    def colorize(text_p, color_code_p, background_color_code_p = nil)
      "\e[#{color_code_p}#{background_color_code_p.nil? ? '' : ";#{background_color_code_p}"}m#{text_p}\e[0m"
    end # colorize

    def pp_backtrace(exception_p)
      # $stderr.print "\r" << (' ' * 50) << "\n"
      stacktrace = exception_p.backtrace.map do |call|
        if parts = call.match(/^(?<file>.+):(?<line>\d+):in `(?<code>.*)'$/)
          file = parts[:file].sub /^#{Regexp.escape(File.join(Dir.getwd, ""))}/, ''
          line = "#{colorize(file, 36)}#{colorize(':', 37)}#{colorize(parts[:line], 32)}#{colorize(':', 37)} #{colorize(parts[:code], 91)}"
        else
          line = colorize(call, 31)
        end
        line
      end
      stacktrace.each { |line| $stderr.print("#{line}\n") }
    end  # pp_backtrace

    def pp_exception(exception_p)
      $stderr.print "#{colorize($g_myname, 36)}#{colorize(': ', 37)}#{colorize('ERROR', 30, 47)}[#{colorize(exception_p.class.to_s, 30, 47)}] #{colorize('--', 37)} #{colorize(exception_p.to_s, 91)}"
      if exception_p.to_s != $ERROR_INFO.to_s
        $stderr.print colorize($ERROR_INFO ? " #{$ERROR_INFO}" : '', 91).to_s
      end
      $stderr.print "\n"
      pp_backtrace(exception_p)
    end  # pp_exception

    def pp_s(obj)
      obj.pretty_inspect
    end

    #SEE:https://stackoverflow.com/questions/13787746/creating-a-thread-safe-temporary-file-name
    def tempfn(dir = '', ext = '')
      filename = begin
        Dir::Tmpname.make_tmpname(["x", ext], nil)
      rescue NoMethodError
        require "securerandom"
        "#{SecureRandom.urlsafe_base64}#{ext}"
      end
      File.join((dir.empty? ? Dir.tmpdir : dir), filename)
    end
  end
end # class It
#
#
#----------
# Handle command line.
#
$g_options = It.optparse
$g_config = It.config($g_options[:config], true)
It.debug "OPTIONS=#{It.pp_s($g_options)}"
It.debug "CONFIG=#{It.pp_s($g_config)}"
# #endregion
#
#
#-----------------------------------------------------------------------
# START HERE!
#-------------
#
token = 'icalyearbox'
nr_codes = 0
h_country_codes = {}
CSV.foreach(File.join("src", "country-codes.csv"), headers: true, col_sep: ";") do |row|
  h_country_codes[row['Code']] = row['Name']
  nr_codes += 1
end
It.info("#{nr_codes} country codes loaded.")

nr_abbreviations = 0
h_month_abbreviations = {}
CSV.foreach(File.join("src", "international-monthnames-abr.csv"), headers: true, col_sep: ";") do |row|
  h_month_abbreviations[row['Code']] = row
  nr_abbreviations += 1
end
It.info("#{nr_abbreviations} abbreviations for month names loaded.")

nr_month_names = 0
h_month_names = {}
CSV.foreach(File.join("src", "international-monthnames.csv"), headers: true, col_sep: ";") do |row|
  h_month_names[row['Code']] = row
  nr_month_names += 1
end
It.info("#{nr_month_names} month names loaded.")


def write_po_file(token, lang_code, country_code, row, h_month_names, h_month_abbreviations)
  #
  # Write PO file.
  fn = "#{token}-#{country_code}.po"
  File.open(fn, 'w') do |f|
    header = <<EOF
msgid ""
msgstr ""
"Project-Id-Version: #{token}\\n"
"Report-Msgid-Bugs-To: \\n"
"POT-Creation-Date: #{Time.now.strftime('%F %T %z')}\\n"
"PO-Revision-Date: \\n"
"Last-Translator: Kai Thoene <k.git.thoene@gmx.de>\\n"
"Language-Team: \\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"X-Poedit-KeywordsList: __;_e\\n"
"X-Poedit-Basepath: .\\n"
"X-Poedit-SearchPath-0: .\\n"

EOF
    f.puts(header)
    row.each do |rowelem|
      key = rowelem[0]
      value = rowelem[1]
      case key
      when 'Code', 'Language'
      else
        f.puts("msgid \"#{key}\"")
        f.puts("msgstr \"#{value}\"")
        f.puts("");
      end
    end
    #
    if h_month_names.key?(lang_code) && h_month_names.key?('en')
      (1..12).each do |nr_month|
        f.puts("msgid \"MONTH-#{h_month_names['en'][nr_month.to_s]}\"")
        f.puts("msgstr \"#{h_month_names[lang_code][nr_month.to_s]}\"")
        f.puts("");
      end
    end
    #
    if h_month_abbreviations.key?(lang_code) && h_month_abbreviations.key?('en')
      (1..12).each do |nr_month|
        f.puts("msgid \"#{h_month_abbreviations['en'][nr_month.to_s]}\"")
        f.puts("msgstr \"#{h_month_abbreviations[lang_code][nr_month.to_s]}\"")
        f.puts("");
      end
    end
    #
  end
end


nr_languages = 0
CSV.foreach(File.join("src", "international-weekdays.csv"), headers: true, col_sep: ";") do |row|
  lang_code = row['Code']
  # Find country code.
  b_found = false
  country_code = ''
  h_country_codes.keys.each do |code|
    if code.start_with?(lang_code) && (code.length == 5)
      country_code = code.gsub(/-/, '_')
      write_po_file(token, lang_code, country_code, row, h_month_names, h_month_abbreviations)
      b_found = true
    end
  end
  if !b_found
    country_code = "#{lang_code.downcase}_#{lang_code.upcase}"
    write_po_file(token, lang_code, country_code, row, h_month_names, h_month_abbreviations)
  end
  nr_languages += 1
end
It.info("#{nr_languages} languages found.")
