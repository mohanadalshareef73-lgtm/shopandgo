# Somar quotation fonts

These fonts come from the supplied `FONTS.zip` archive. The quotation uses
Regular, Bold, RegularItalic and BoldItalic; the original `.otf` files are
retained alongside the runtime `.ttf` files.

mPDF cannot read the archive's OpenType CFF outlines. The TrueType copies were
converted with fontTools: each glyph was drawn through `Cu2QuPen` into
`TTGlyphPen` (maximum error 0.5 font units, reversed contour direction), then
`FontBuilder` created the `glyf`, `loca`, `maxp` and `post` tables. Glyph order,
character mappings and the original Arabic GDEF, GSUB and GPOS tables were
preserved. No font conversion is needed at runtime.

`SheinQuotationPdf` registers the family as `somar` with OpenType layout enabled
and embeds subsets in the PDF, so clients do not need to install the font.
