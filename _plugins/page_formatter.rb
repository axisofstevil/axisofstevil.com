require 'rubygems'
require 'bundler/setup'

require 'nokogiri'

module Jekyll
  module PageFormatter
    def format_collection(text)
        @html = Nokogiri::HTML::fragment(text)

        add_grid_breaks()

        @html.to_html
    end

    def format_page(text)
        @html = Nokogiri::HTML::fragment(text)

        # add_section_breaks()

        add_gallery_classes()

        @html.to_html
    end

    def add_grid_breaks
        text = @html.to_html

        code = %q{</article><article>}

        hrPattern = /<hr[^>]*>/

        text = '<div class="posts"><article>' + text.gsub!(hrPattern, code) + '</article></div>'

        @html = Nokogiri::HTML::fragment(text)
    end

    def add_section_breaks
        text = @html.to_html

        code = %q{        </div>
    </section>
    <section>
        <div class="prose">}

        hrPattern = /<hr[^>]*>/

        text = text.gsub(hrPattern, code)

        @html = Nokogiri::HTML::fragment(text)
    end

    def add_gallery_classes
        text = @html.to_html
        @html.css('ul').each do |ul|
            box = '<div class="box alt"><div class="row 50% uniform">'
            images = ul.css("img.gallery-item")
            images.each do |image|
                box += "<div class=\"3u\"><span class=\"image fit\">#{image}</span></div>"
            end
            box += '</div></div>'
            text = text.gsub(ul.to_html, box)
            @html = Nokogiri::HTML::fragment(text)
        end

    end
  end
end

Liquid::Template.register_filter(Jekyll::PageFormatter)
