<?php
/**
 * Page templates and their editable fields.
 * Every page has standard fields (title, slug, hero, SEO). Templates add their own
 * editable `data` fields below — these appear automatically in Admin → Pages → Edit.
 */
declare(strict_types=1);

function iconTextList(string $item = 'Item'): array
{
    return ['type' => 'list', 'item' => $item, 'fields' => [
        'icon' => ['type' => 'icon', 'label' => 'Icon'],
        'title' => ['type' => 'text', 'label' => 'Title'],
        'text' => ['type' => 'textarea', 'label' => 'Text', 'rows' => 2],
    ]];
}

function ctaFields(string $title, string $text): array
{
    return [
        'cta_heading' => ['type' => 'heading', 'label' => 'Call-to-action band'],
        'cta_title' => ['type' => 'text', 'label' => 'CTA title', 'default' => $title],
        'cta_text' => ['type' => 'textarea', 'label' => 'CTA text', 'rows' => 2, 'default' => $text],
    ];
}

function page_templates(): array
{
    $ctaDefault = ["Looking for Reliable Industrial Components?", "Let's discuss your engineering requirements and find the right manufacturing solution for your business."];

    return [
        'home' => [
            'label' => 'Home page',
            'content' => false,
            'fields' => [
                'hero_trust' => ['type' => 'lines', 'label' => 'Hero trust points (one per line)', 'default' => ['Precision Manufacturing', 'Industrial-Grade Quality', 'Custom Engineering Solutions']],
                'hero_image_alt' => ['type' => 'text', 'label' => 'Hero image alt text', 'default' => 'Heavy-duty gantry cranes loading a container ship at an industrial port'],
                'about_heading' => ['type' => 'heading', 'label' => 'About section'],
                'about_title' => ['type' => 'text', 'label' => 'Title', 'default' => 'Built on Engineering Excellence'],
                'about_text' => ['type' => 'html', 'label' => 'Text', 'toolbar' => 'basic', 'default' => '<p>[company_name] is an industrial engineering and manufacturing solutions provider specialising in crane components and hydraulic systems. We design, machine, assemble and test the parts that keep heavy equipment lifting, moving and working.</p><p>From single replacement parts to fully engineered hydraulic systems, our team works from drawing to delivery with one goal: components that fit right, perform reliably and last under real industrial loads.</p>'],
                'about_image' => ['type' => 'image', 'label' => 'Image', 'default' => 'https://images.unsplash.com/photo-1504328345606-18bbc8c9d7d1?auto=format&fit=crop&w=1100&q=80'],
                'about_features' => iconTextList('Feature') + ['label' => 'Feature points', 'default' => [
                    ['icon' => 'factory', 'title' => 'Manufacturing Expertise', 'text' => 'Machining, fabrication and assembly under one roof.'],
                    ['icon' => 'badge-check', 'title' => 'Quality-Focused Production', 'text' => 'Inspection built into every step, not added at the end.'],
                    ['icon' => 'ruler', 'title' => 'Engineering Capability', 'text' => 'Design, reverse engineering and application support.'],
                    ['icon' => 'handshake', 'title' => 'Customer-Oriented Solutions', 'text' => 'Built to your drawings, duty cycle and timeline.'],
                ]],
                'products_heading' => ['type' => 'heading', 'label' => 'Products section'],
                'products_title' => ['type' => 'text', 'label' => 'Title', 'default' => 'Components Engineered for Heavy Duty'],
                'products_lead' => ['type' => 'textarea', 'label' => 'Intro', 'rows' => 2, 'default' => 'Crane parts, hydraulic cylinders and complete hydraulic systems, manufactured to specification and tested before dispatch.'],
                'why_heading' => ['type' => 'heading', 'label' => 'Why choose us'],
                'why_title' => ['type' => 'text', 'label' => 'Title', 'default' => 'The Standard Behind Every Component'],
                'why_text' => ['type' => 'textarea', 'label' => 'Text', 'rows' => 2, 'default' => 'Heavy equipment is only as dependable as its weakest part. We engineer every component so that it never becomes that part.'],
                'why_items' => iconTextList('Reason') + ['label' => 'Reasons', 'default' => [
                    ['icon' => 'crosshair', 'title' => 'Precision Engineering', 'text' => 'Tight tolerances and controlled finishes for parts that fit first time.'],
                    ['icon' => 'shield-check', 'title' => 'Quality Assurance', 'text' => 'Material checks, dimensional inspection and documented test results.'],
                    ['icon' => 'cog', 'title' => 'Advanced Manufacturing', 'text' => 'CNC machining, fabrication and assembly controlled in-house.'],
                    ['icon' => 'drafting-compass', 'title' => 'Custom-Built Solutions', 'text' => 'Made to your drawings, samples or application requirements.'],
                    ['icon' => 'gauge', 'title' => 'Reliable Performance', 'text' => 'Designed for continuous duty, shock loads and harsh environments.'],
                    ['icon' => 'headset', 'title' => 'Technical Support', 'text' => 'Application advice, installation guidance and after-sales service.'],
                ]],
                'process_title' => ['type' => 'text', 'label' => 'Process section title', 'default' => 'From Enquiry to Installed Component'],
                'industries_title' => ['type' => 'text', 'label' => 'Industries section title', 'default' => 'Trusted Where Equipment Works Hardest'],
                'mfg_heading' => ['type' => 'heading', 'label' => 'Manufacturing teaser'],
                'mfg_title' => ['type' => 'text', 'label' => 'Title', 'default' => 'From Raw Steel to Tested Assembly'],
                'mfg_text' => ['type' => 'textarea', 'label' => 'Text', 'rows' => 2, 'default' => 'Controlling each stage of production in-house lets us hold tolerances, protect lead times and stand behind every part we ship.'],
                'mfg_image' => ['type' => 'image', 'label' => 'Image', 'default' => 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=1100&q=80'],
                'blog_title' => ['type' => 'text', 'label' => 'Latest articles title', 'default' => 'Insights & Technical Guides'],
            ] + ctaFields(...$ctaDefault),
        ],
        'about' => [
            'label' => 'About page',
            'content' => true,
            'content_label' => 'Company story',
            'fields' => [
                'story_title' => ['type' => 'text', 'label' => 'Story title', 'default' => 'Engineering Partners for Heavy Industry'],
                'mission' => ['type' => 'textarea', 'label' => 'Mission', 'default' => 'To keep our customers’ heavy equipment working by delivering precision-engineered components and hydraulic solutions that are reliable, well-documented and delivered when they are needed.'],
                'vision' => ['type' => 'textarea', 'label' => 'Vision', 'default' => 'To be the engineering partner industrial companies trust first for crane components and hydraulic systems — known for quality, responsiveness and technical depth.'],
                'values' => iconTextList('Value') + ['label' => 'Values', 'default' => [
                    ['icon' => 'crosshair', 'title' => 'Precision', 'text' => 'We measure, verify and document — because a part that almost fits does not fit.'],
                    ['icon' => 'shield-check', 'title' => 'Integrity', 'text' => 'We quote honestly, report findings openly and stand behind our work.'],
                    ['icon' => 'handshake', 'title' => 'Partnership', 'text' => 'We work as an extension of our customers’ maintenance and engineering teams.'],
                    ['icon' => 'lightbulb', 'title' => 'Improvement', 'text' => 'We look for ways to extend service life and reduce downtime on every job.'],
                ]],
                'approach_title' => ['type' => 'text', 'label' => 'Approach title', 'default' => 'How We Deliver'],
            ] + ctaFields('Let’s Build Something That Lasts', 'Tell us about your equipment and requirements — our engineers will recommend the right solution.'),
        ],
        'products' => [
            'label' => 'Products catalogue',
            'content' => false,
            'fields' => [
                'intro_items' => iconTextList('Point') + ['label' => 'Capability points (below catalogue)', 'default' => [
                    ['icon' => 'file-text', 'title' => 'Built to Drawing', 'text' => 'Send OEM drawings or specifications and we manufacture to them exactly.'],
                    ['icon' => 'scan-line', 'title' => 'Reverse Engineering', 'text' => 'No drawing? We measure worn samples and produce an approved drawing.'],
                    ['icon' => 'clipboard-check', 'title' => 'Documented Quality', 'text' => 'Inspection reports and test records are available with every order.'],
                ]],
            ] + ctaFields('Don’t See the Part You Need?', 'We manufacture custom and obsolete components from drawings or samples. Send us the details.'),
        ],
        'industries' => [
            'label' => 'Industries overview',
            'content' => false,
            'fields' => ctaFields('Your Industry, Your Requirements', 'Tell us about your equipment and operating conditions and we will recommend the right components.'),
        ],
        'manufacturing' => [
            'label' => 'Manufacturing page',
            'content' => false,
            'fields' => [
                'capabilities_title' => ['type' => 'text', 'label' => 'Capabilities title', 'default' => 'In-House Manufacturing Capabilities'],
                'capabilities' => ['type' => 'list', 'item' => 'Capability', 'label' => 'Capabilities (also shown on the home page)', 'fields' => [
                    'icon' => ['type' => 'icon'], 'name' => ['type' => 'text', 'label' => 'Name'],
                    'text' => ['type' => 'textarea', 'label' => 'Description', 'rows' => 2], 'points' => ['type' => 'lines', 'label' => 'Bullet points (one per line)', 'rows' => 3],
                ], 'default' => []],
                'process_title' => ['type' => 'text', 'label' => 'Process title', 'default' => 'A Controlled, Documented Workflow'],
                'facility_title' => ['type' => 'text', 'label' => 'Facility title', 'default' => 'Plant & Equipment'],
                'facility_text' => ['type' => 'textarea', 'label' => 'Facility text', 'default' => 'An overview of our key production equipment.'],
                'facility_image' => ['type' => 'image', 'label' => 'Facility image', 'default' => 'https://images.unsplash.com/photo-1581092162384-8987c1d64718?auto=format&fit=crop&w=1100&q=80'],
                'equipment' => ['type' => 'list', 'item' => 'Machine', 'label' => 'Equipment list', 'fields' => ['label' => ['type' => 'text', 'label' => 'Equipment'], 'value' => ['type' => 'text', 'label' => 'Quantity / detail']], 'default' => []],
            ] + ctaFields('Put Our Workshop to Work', 'From single urgent parts to repeat batch production — send your drawings for a quotation.'),
        ],
        'quality' => [
            'label' => 'Quality page',
            'content' => false,
            'fields' => [
                'stages_title' => ['type' => 'text', 'label' => 'Inspection stages title', 'default' => 'Four Gates Every Part Passes Through'],
                'stages' => iconTextList('Stage') + ['label' => 'Inspection stages', 'default' => [
                    ['icon' => 'package-check', 'title' => 'Material Verification', 'text' => 'Incoming steel, castings, forgings and seals are checked against specification and certificates before production starts.'],
                    ['icon' => 'ruler', 'title' => 'In-Process Inspection', 'text' => 'Critical dimensions are measured at each machining stage, so deviations are caught early rather than at final inspection.'],
                    ['icon' => 'gauge', 'title' => 'Pressure & Function Testing', 'text' => 'Hydraulic cylinders, pumps and power units are tested for pressure, leakage and function on dedicated rigs.'],
                    ['icon' => 'clipboard-check', 'title' => 'Final Inspection & Release', 'text' => 'Final dimensional, visual and documentation checks are completed before a part is released for dispatch.'],
                ]],
                'docs_title' => ['type' => 'text', 'label' => 'Documentation title', 'default' => 'Traceable Records You Can Rely On'],
                'docs_text' => ['type' => 'textarea', 'label' => 'Documentation text', 'rows' => 2, 'default' => 'On request, deliveries can include the records your maintenance and quality teams need.'],
                'docs_items' => ['type' => 'lines', 'label' => 'Documents (one per line)', 'default' => ['Dimensional inspection reports', 'Material test certificates (where supplied by the mill)', 'Pressure and leak test records', 'Hardness test results', 'Hydraulic circuit diagrams and drawings']],
                'docs_image' => ['type' => 'image', 'label' => 'Documentation image', 'default' => 'https://images.unsplash.com/photo-1581092446327-9b52bd1570c2?auto=format&fit=crop&w=1100&q=80'],
                'standards_title' => ['type' => 'text', 'label' => 'Standards title', 'default' => 'Standards & Certifications'],
                'standards_text' => ['type' => 'textarea', 'label' => 'Standards text', 'help' => 'Only list certifications (ISO etc.) that have been verified.', 'default' => 'Details of applicable standards, certifications and approvals are available on request. Please mention any customer-specific quality requirements in your enquiry.'],
            ] + ctaFields(...$ctaDefault),
        ],
        'contact' => [
            'label' => 'Contact page',
            'content' => false,
            'fields' => [
                'form_title' => ['type' => 'text', 'label' => 'Form title', 'default' => 'Send Us a Message'],
                'map_embed' => ['type' => 'url', 'label' => 'Google Maps embed URL', 'help' => 'Google Maps → Share → Embed a map → copy only the <code>src</code> URL. Leave empty to show a placeholder.'],
                'faq_title' => ['type' => 'text', 'label' => 'FAQ title', 'default' => 'Frequently Asked'],
                'faq' => ['type' => 'list', 'item' => 'Question', 'label' => 'FAQ', 'fields' => ['q' => ['type' => 'text', 'label' => 'Question', 'wide' => true], 'a' => ['type' => 'textarea', 'label' => 'Answer', 'rows' => 3]], 'default' => []],
            ],
        ],
        'quote' => [
            'label' => 'Quote request page',
            'content' => false,
            'fields' => [
                'next_title' => ['type' => 'text', 'label' => '“What happens next” title', 'default' => 'What Happens Next'],
                'next_steps' => ['type' => 'list', 'item' => 'Step', 'label' => 'Steps', 'fields' => ['title' => ['type' => 'text'], 'text' => ['type' => 'textarea', 'rows' => 2]], 'default' => [
                    ['title' => 'Review', 'text' => 'An engineer reviews your requirement and drawings.'],
                    ['title' => 'Clarify', 'text' => 'We contact you if any technical details need confirming.'],
                    ['title' => 'Quotation', 'text' => 'You receive a quotation with price, lead time and scope.'],
                ]],
                'urgent_title' => ['type' => 'text', 'label' => 'Urgent box title', 'default' => 'Urgent Breakdown?'],
                'urgent_text' => ['type' => 'textarea', 'label' => 'Urgent box text', 'rows' => 2, 'default' => 'Call us directly for urgent spare parts and repairs.'],
            ],
        ],
        'blog' => [
            'label' => 'Blog index',
            'content' => false,
            'fields' => [
                'per_page' => ['type' => 'number', 'label' => 'Posts per page', 'default' => 9],
            ] + ctaFields('Have a Technical Question?', 'Our engineers are happy to help with component selection, repairs and custom manufacturing.'),
        ],
        'legal' => [
            'label' => 'Legal / policy page',
            'content' => true,
            'content_label' => 'Policy text',
            'content_help' => 'Use <strong>Heading 2</strong> for sections — they become the numbered table of contents. Shortcodes: [company_name] [legal_name] [email] [privacy_email] [phone] [address] [jurisdiction] [contact_block] [cookie_table] [last_updated] [year]',
            'fields' => [],
        ],
        'sitemap' => ['label' => 'HTML sitemap', 'content' => false, 'fields' => []],
        'default' => [
            'label' => 'Standard page',
            'content' => true,
            'content_label' => 'Page content',
            'content_help' => 'Shortcodes such as [company_name], [email] and [phone] are replaced automatically.',
            'fields' => [
                'show_toc' => ['type' => 'checkbox', 'label' => 'Table of contents', 'text' => 'Show a table of contents built from Heading 2 titles'],
                'show_cta' => ['type' => 'checkbox', 'label' => 'CTA band', 'text' => 'Show the call-to-action band at the bottom', 'default' => true],
            ] + ctaFields(...$ctaDefault),
        ],
    ];
}

function template_def(string $template): array
{
    return page_templates()[$template] ?? page_templates()['default'];
}

/** Default data values for a template (used when seeding and when fields are added later). */
function template_defaults(string $template): array
{
    $out = [];
    foreach (template_def($template)['fields'] as $k => $def) {
        if (array_key_exists('default', $def)) $out[$k] = $def['default'];
    }
    return $out;
}

/** Page data merged with defaults so newly added template fields always have a value. */
function page_data(array $page): array
{
    return array_merge(template_defaults($page['template']), json_decode_array($page['data'] ?? null));
}
