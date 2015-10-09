require 'rubygems'
require 'bundler/setup'

require 'nokogiri'

module Jekyll
  module PageFormatter
    def format_page(text)
        @html = Nokogiri::HTML::fragment(text)

        add_section_breaks()

        add_gallery_classes()

        @html.to_html
    end

    def add_section_breaks()
        text = @html.to_html

        code = %q{        </div>
    </section>
    <section>
        <div class="prose">}

        hrPattern = /<hr[^>]*>/

        text = text.gsub(hrPattern, code)

        @html = Nokogiri::HTML::fragment(text)
    end

    def add_gallery_classes()

        @html.css('ul').each do |ul|
            images = ul.css("img.gallery-item")

            if images.any?
                ul['class'] = 'gallery'
            end
        end

    end
  end
end

Liquid::Template.register_filter(Jekyll::PageFormatter)
