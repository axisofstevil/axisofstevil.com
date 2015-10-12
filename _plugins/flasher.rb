module Jekyll
  module Flasher
    def parse_flash(text)
        content_baseurl = @context.registers[:site].config['content_baseurl']

        html = %q{          <div class="flex-video">
            <object type="application/x-shockwave-flash" data="jekyll_flash_media_url" width="100%" height="400">
                <param name="movie" value="jekyll_flash_media_url" />
                <param name="quality" value="high"/>
            </object>
        </div>
        }

        text.gsub(/\<p\>!#flash\(([^\)]+)\)\<\/p\>/i) {
            mediaUrl = $1
            if mediaUrl.start_with?('/')
                mediaUrl.prepend(content_baseurl)
            end
            html.gsub(/jekyll_flash_media_url/, mediaUrl)
        }
    end
  end
end

Liquid::Template.register_filter(Jekyll::Flasher)
