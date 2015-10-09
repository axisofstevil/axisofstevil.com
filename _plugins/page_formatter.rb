require 'nokogiri'

module Jekyll
  module PageFormatter
    def format_page(text)
        @html = Nokogiri::HTML(text)

        add_section_breaks()
        add_gallery_classes()

        @html.to_html
    end

    def add_section_breaks()
        text = @html.to_html

        code = %q{    </div>
</section>
<section>
    <div class="prose">}

        text.gsub(/<hr \/>/, code)

        @html = Nokogiri::HTML(text)
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
