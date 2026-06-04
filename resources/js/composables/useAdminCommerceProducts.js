import { router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { centsToMajor, majorToCents, transformMoneyFields } from '@/utils/currency'
import { confirmDialog, promptDialog } from '@/services/dialogService'

const defaultProductForm = () => ({
    title: '',
    description: '',
    features_text: '',
    attributes_text: '',
    attribute_options: [],
    variants: [],
    image_url: '',
    image_urls_text: '',
    image_upload: null,
    image_uploads: [],
    category: 'product',
    product_type: 'single',
    sku: '',
    is_shippable: true,
    manages_stock: false,
    stock_quantity: '',
    tax_class: 'standard',
    return_policy_type: 'standard',
    return_window_days: 14,
    digital_delivery_note: '',
    price_cents: '',
    currency: 'EUR',
    status: 'draft',
    commission_percent: 10,
})

const emptyDeleteModal = () => ({
    open: false,
    product: null,
    confirmation: '',
    processing: false,
})

export function useAdminCommerceProducts() {
    const stockAdjustments = ref({})
    const productForm = useForm(defaultProductForm())
    const productAttributeRows = ref([{ name: '', values: [] }])
    const productFeatureRows = ref([''])
    const productVariantRows = ref([])
    const rejectionModal = useForm({
        open: false,
        product: null,
        selectedReason: '',
        reason: '',
    })
    const productDeleteModal = ref(emptyDeleteModal())
    const editProductForm = useForm({
        open: false,
        product: null,
        ...defaultProductForm(),
        stock_quantity: 0,
        low_stock_threshold: 0,
        rejection_reason: '',
    })
    const editAttributeRows = ref([{ name: '', values: [] }])
    const editFeatureRows = ref([''])
    const editVariantRows = ref([])

    const rejectionReasons = [
        { value: 'missing_required_info', label: 'Pflichtangaben fehlen', text: 'Bitte ergänze die fehlenden Pflichtangaben wie Beschreibung, Preis, Kategorie oder Lieferinformationen.' },
        { value: 'unclear_offer', label: 'Angebot ist unklar', text: 'Das Angebot ist für Käufer noch nicht eindeutig genug beschrieben. Bitte erkläre Inhalt, Umfang und Ablauf genauer.' },
        { value: 'invalid_category', label: 'Falsche Kategorie', text: 'Das Angebot passt nicht zur gewählten Kategorie. Bitte Wähle die passende Marketplace-Kategorie.' },
        { value: 'bad_images', label: 'Bilder fehlen oder sind ungeeignet', text: 'Bitte lade passende, klare Bilder hoch. Platzhalter, unscharfe oder irrefuehrende Bilder können nicht freigegeben werden.' },
        { value: 'price_or_tax_issue', label: 'Preis, Steuer oder Versand unklar', text: 'Preis, Steuerklasse, Versand oder Lieferbedingungen sind nicht plausibel genug angegeben.' },
        { value: 'prohibited_content', label: 'Nicht erlaubter Inhalt', text: 'Dieses Angebot enthält Inhalte oder Leistungen, die auf Airmius nicht veröffentlicht werden können.' },
        { value: 'quality_review', label: 'Qualitätsprüfung nicht bestanden', text: 'Das Angebot erfüllt aktuell nicht die Qualitätsanforderungen für den Marketplace.' },
        { value: 'duplicate', label: 'Doppeltes Angebot', text: 'Ein sehr ähnliches Angebot existiert bereits. Bitte bearbeite das bestehende Angebot statt ein neues einzureichen.' },
        { value: 'custom', label: 'Eigener Grund', text: '' },
    ]

    const attributePresets = [
        { name: 'Farbe', values: ['Schwarz', 'Weiß', 'Rot', 'Blau', 'Grün', 'Gelb', 'Orange', 'Grau'] },
        { name: 'Größe', values: ['XS', 'S', 'M', 'L', 'XL', 'XXL', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46'] },
        { name: 'Material', values: ['Baumwolle', 'Polyester', 'Leder', 'Mesh', 'Kunststoff', 'Metall'] },
        { name: 'Dauer', values: ['30 Minuten', '60 Minuten', '90 Minuten', '1 Tag', '2 Tage', 'Wochenende'] },
        { name: 'Lieferart', values: ['E-Mail', 'Download', 'Online-Zugang', 'Vor Ort', 'Versand'] },
    ]

    const presetValuesFor = (name) => attributePresets.find((preset) => preset.name === name)?.values || []

    const normalizeAttributeRows = (rows) => rows
        .map((row) => ({
            name: String(row.name || '').trim(),
            values: Array.isArray(row.values)
                ? row.values.map((value) => String(value || '').trim()).filter(Boolean)
                : String(row.values || '').split(/[|,]/).map((value) => value.trim()).filter(Boolean),
        }))
        .filter((row) => row.name && row.values.length)

    const normalizeVariantRows = (rows) => rows
        .map((row) => ({
            sku: String(row.sku || '').trim(),
            price_cents: row.price_cents === '' || row.price_cents === null ? null : majorToCents(row.price_cents),
            stock_quantity: row.stock_quantity === '' || row.stock_quantity === null ? null : Number(row.stock_quantity),
            image_url: String(row.image_url || '').trim(),
            attributes: Object.entries(row.attributes || {})
                .map(([name, value]) => ({ name, value: String(value || '').trim() }))
                .filter((attribute) => attribute.name && attribute.value),
        }))
        .filter((row) => row.attributes.length)

    const rowsToAttributeText = (rows) => rows
        .map((row) => ({
            name: String(row.name || '').trim(),
            values: Array.isArray(row.values) ? row.values.join(' | ') : String(row.values || '').trim(),
        }))
        .filter((row) => row.name && row.values)
        .map((row) => `${row.name}: ${row.values}`)
        .join('\n')

    const rowsToFeatureText = (rows) => rows
        .map((feature) => String(feature || '').trim())
        .filter(Boolean)
        .join('\n')

    const galleryUrlsText = (product) => (product.gallery_images || [])
        .filter((image) => image && image !== product.image_url)
        .join('\n')

    const productPayload = (product, overrides = {}) => ({
        title: product.title,
        description: product.description,
        features_text: (product.features || []).join('\n'),
        attributes_text: (product.product_attributes || []).map((attribute) => `${attribute.name}: ${attribute.value}`).join('\n'),
        attribute_options: product.attribute_options || [],
        variants: product.variants || [],
        image_url: product.image_url || '',
        image_urls_text: galleryUrlsText(product),
        category: product.category,
        product_type: product.product_type || 'single',
        price_cents: product.price_cents,
        currency: product.currency || 'EUR',
        status: product.status,
        rejection_reason: product.rejection_reason || null,
        commission_percent: product.commission_percent ?? 10,
        sku: product.sku || '',
        is_shippable: Boolean(product.is_shippable),
        manages_stock: Boolean(product.manages_stock),
        stock_quantity: product.stock_quantity ?? 0,
        low_stock_threshold: product.low_stock_threshold || 0,
        tax_class: product.tax_class || 'standard',
        return_policy_type: product.return_policy_type || 'standard',
        return_window_days: product.return_window_days ?? 14,
        digital_delivery_note: product.digital_delivery_note || '',
        ...overrides,
    })

    const addProductAttributeRow = () => {
        productAttributeRows.value.push({ name: '', values: [] })
    }

    const removeProductAttributeRow = (index) => {
        productAttributeRows.value.splice(index, 1)
        if (!productAttributeRows.value.length) addProductAttributeRow()
    }

    const addProductVariantRow = () => {
        productVariantRows.value.push({ sku: '', price_cents: productForm.price_cents || '', stock_quantity: '', image_url: '', attributes: {} })
    }

    const removeProductVariantRow = (index) => {
        productVariantRows.value.splice(index, 1)
    }

    const addProductFeatureRow = () => {
        productFeatureRows.value.push('')
    }

    const removeProductFeatureRow = (index) => {
        productFeatureRows.value.splice(index, 1)
        if (!productFeatureRows.value.length) addProductFeatureRow()
    }

    const addEditAttributeRow = () => {
        editAttributeRows.value.push({ name: '', values: [] })
    }

    const removeEditAttributeRow = (index) => {
        editAttributeRows.value.splice(index, 1)
        if (!editAttributeRows.value.length) addEditAttributeRow()
    }

    const addEditVariantRow = () => {
        editVariantRows.value.push({ sku: '', price_cents: editProductForm.price_cents || '', stock_quantity: '', image_url: '', attributes: {} })
    }

    const removeEditVariantRow = (index) => {
        editVariantRows.value.splice(index, 1)
    }

    const addEditFeatureRow = () => {
        editFeatureRows.value.push('')
    }

    const removeEditFeatureRow = (index) => {
        editFeatureRows.value.splice(index, 1)
        if (!editFeatureRows.value.length) addEditFeatureRow()
    }

    const setProductImageUpload = (event) => {
        productForm.image_upload = event.target.files?.[0] || null
    }

    const setProductGalleryUploads = (event) => {
        productForm.image_uploads = Array.from(event.target.files || [])
    }

    const setEditProductImageUpload = (event) => {
        editProductForm.image_upload = event.target.files?.[0] || null
    }

    const setEditProductGalleryUploads = (event) => {
        editProductForm.image_uploads = Array.from(event.target.files || [])
    }

    const storeProduct = () => {
        productForm.attributes_text = rowsToAttributeText(productAttributeRows.value)
        productForm.features_text = rowsToFeatureText(productFeatureRows.value)
        productForm.attribute_options = normalizeAttributeRows(productAttributeRows.value)
        productForm.variants = normalizeVariantRows(productVariantRows.value)

        productForm
            .transform((data) => transformMoneyFields(data, ['price_cents']))
            .post(route('admin.commerce.products.store'), {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: () => {
                    productForm.reset('title', 'description', 'features_text', 'attributes_text', 'attribute_options', 'variants', 'image_url', 'image_urls_text', 'image_upload', 'image_uploads', 'sku', 'digital_delivery_note')
                    productAttributeRows.value = [{ name: '', values: [] }]
                    productFeatureRows.value = ['']
                    productVariantRows.value = []
                },
            })
    }

    const updateProductStatus = async (product, status) => {
        if (status === 'rejected') {
            rejectionModal.open = true
            rejectionModal.product = product
            rejectionModal.selectedReason = ''
            rejectionModal.reason = product.rejection_reason || ''
            return
        }

        if (status === 'published' && product.quality_issues?.length) {
            const confirmed = await confirmDialog({
                title: 'Produkt trotzdem freigeben?',
                message: `Dieses Produkt hat noch ${product.quality_issues.length} Qualitätsproblem(e):\n\n${product.quality_issues.join('\n')}\n\nTrotzdem freigeben?`,
                confirmLabel: 'Trotzdem freigeben',
            })

            if (!confirmed) {
                router.reload({ only: ['products'], preserveScroll: true })
                return
            }
        }

        router.put(route('admin.commerce.products.update', product.id), productPayload(product, { status }), { preserveScroll: true })
    }

    const submitRejection = () => {
        const product = rejectionModal.product
        if (!product || !rejectionModal.reason.trim()) return

        router.put(route('admin.commerce.products.update', product.id), productPayload(product, {
            status: 'rejected',
            rejection_reason: rejectionModal.reason,
        }), {
            preserveScroll: true,
            onSuccess: () => {
                rejectionModal.open = false
                rejectionModal.product = null
                rejectionModal.selectedReason = ''
                rejectionModal.reason = ''
            },
        })
    }

    const openDeleteProduct = (product) => {
        productDeleteModal.value = {
            open: true,
            product,
            confirmation: '',
            processing: false,
        }
    }

    const closeDeleteProduct = () => {
        productDeleteModal.value = emptyDeleteModal()
    }

    const setProductDeleteConfirmation = (confirmation) => {
        productDeleteModal.value.confirmation = confirmation
    }

    const destroyProduct = () => {
        const product = productDeleteModal.value.product
        if (!product || productDeleteModal.value.confirmation !== 'delete') return

        productDeleteModal.value.processing = true
        router.delete(route('admin.commerce.products.destroy', product.id), {
            preserveScroll: true,
            only: ['products', 'summary', 'auditLogs', 'sellerReports'],
            data: {
                confirmation: productDeleteModal.value.confirmation,
            },
            onSuccess: closeDeleteProduct,
            onFinish: () => {
                productDeleteModal.value.processing = false
            },
        })
    }

    const selectRejectionReason = () => {
        const selected = rejectionReasons.find((reason) => reason.value === rejectionModal.selectedReason)
        rejectionModal.reason = selected?.text || ''
    }

    const updateProductStock = (product) => {
        router.put(route('admin.commerce.products.update', product.id), productPayload(product, {
            manages_stock: true,
            stock_quantity: Math.max(0, Number(product.stock_quantity || 0)),
        }), { preserveScroll: true })
    }

    const inventoryAdjustmentKey = (product, inventory) => `${product.id}:${inventory.id}`

    const inventoryAdjustment = (product, inventory) => stockAdjustments.value[inventoryAdjustmentKey(product, inventory)] || { quantity_delta: '', note: '' }

    const setInventoryAdjustment = (product, inventory, field, value) => {
        const key = inventoryAdjustmentKey(product, inventory)
        stockAdjustments.value[key] = {
            ...inventoryAdjustment(product, inventory),
            [field]: value,
        }
    }

    const adjustInventoryStock = (product, inventory) => {
        const adjustment = inventoryAdjustment(product, inventory)
        const quantityDelta = Number(adjustment.quantity_delta || 0)
        if (!quantityDelta) return

        router.post(route('admin.commerce.products.stock.adjust', product.id), {
            marketplace_product_inventory_id: inventory.id,
            quantity_delta: quantityDelta,
            note: adjustment.note || `Admin-Korrektur ${inventory.country_code}`,
        }, {
            preserveScroll: true,
            only: ['products', 'summary', 'auditLogs', 'sellerReports'],
            onSuccess: () => {
                stockAdjustments.value[inventoryAdjustmentKey(product, inventory)] = { quantity_delta: '', note: '' }
            },
        })
    }

    const adjustGlobalStock = (product) => {
        const key = `${product.id}:global`
        const adjustment = stockAdjustments.value[key] || { quantity_delta: '', note: '' }
        const quantityDelta = Number(adjustment.quantity_delta || 0)
        if (!quantityDelta) return

        router.post(route('admin.commerce.products.stock.adjust', product.id), {
            quantity_delta: quantityDelta,
            note: adjustment.note || 'Admin-Korrektur global',
        }, {
            preserveScroll: true,
            only: ['products', 'summary', 'auditLogs', 'sellerReports'],
            onSuccess: () => {
                stockAdjustments.value[key] = { quantity_delta: '', note: '' }
            },
        })
    }

    const updateSellerApplication = async (application, status) => {
        const reviewNote = status === 'rejected'
            ? await promptDialog({
                title: 'Shop-Antrag ablehnen',
                message: 'Bitte gib den Ablehnungsgrund ein. Diese Notiz wird für die Prüfung gespeichert.',
                inputLabel: 'Ablehnungsgrund',
                multiline: true,
                required: true,
                confirmLabel: 'Ablehnen',
                danger: true,
            })
            : ''

        if (status === 'rejected' && (!reviewNote || !reviewNote.trim())) return

        router.put(route('admin.commerce.seller-applications.update', application.id), {
            status,
            review_note: reviewNote,
        }, { preserveScroll: true })
    }

    const openEditProduct = (product) => {
        editProductForm.open = true
        editProductForm.product = product
        editProductForm.title = product.title || ''
        editProductForm.description = product.description || ''
        editProductForm.features_text = (product.features || []).join('\n')
        editProductForm.attributes_text = (product.product_attributes || []).map((attribute) => `${attribute.name}: ${attribute.value}`).join('\n')
        editProductForm.attribute_options = product.attribute_options || []
        editProductForm.variants = product.variants || []
        editProductForm.image_url = product.image_url || ''
        editProductForm.image_urls_text = galleryUrlsText(product)
        editProductForm.image_upload = null
        editProductForm.image_uploads = []
        editProductForm.category = product.category || 'product'
        editProductForm.product_type = product.product_type || 'single'
        editProductForm.sku = product.sku || ''
        editProductForm.is_shippable = Boolean(product.is_shippable)
        editProductForm.manages_stock = Boolean(product.manages_stock)
        editProductForm.stock_quantity = product.stock_quantity ?? 0
        editProductForm.low_stock_threshold = product.low_stock_threshold || 0
        editProductForm.tax_class = product.tax_class || 'standard'
        editProductForm.return_policy_type = product.return_policy_type || 'standard'
        editProductForm.return_window_days = product.return_window_days ?? 14
        editProductForm.digital_delivery_note = product.digital_delivery_note || ''
        editProductForm.price_cents = centsToMajor(product.price_cents || 0)
        editProductForm.currency = product.currency || 'EUR'
        editProductForm.status = product.status || 'draft'
        editProductForm.rejection_reason = product.rejection_reason || ''
        editProductForm.commission_percent = product.commission_percent ?? 10
        editAttributeRows.value = product.attribute_options?.length
            ? product.attribute_options.map((attribute) => ({ name: attribute.name || '', values: attribute.values || [] }))
            : (product.product_attributes?.length
                ? product.product_attributes.map((attribute) => ({ name: attribute.name || '', values: String(attribute.value || '').split(/[|,]/).map((value) => value.trim()).filter(Boolean) }))
                : [{ name: '', values: [] }])
        editFeatureRows.value = product.features?.length ? [...product.features] : ['']
        editVariantRows.value = product.variants?.length
            ? product.variants.map((variant) => ({
                sku: variant.sku || '',
                price_cents: centsToMajor(variant.price_cents ?? product.price_cents ?? 0),
                stock_quantity: variant.stock_quantity ?? '',
                image_url: variant.image_url || '',
                attributes: Object.fromEntries((variant.attributes || []).map((attribute) => [attribute.name, attribute.value])),
            }))
            : []
    }

    const closeEditProduct = () => {
        editProductForm.open = false
        editProductForm.product = null
    }

    const submitEditProduct = () => {
        if (!editProductForm.product) return

        editProductForm.attributes_text = rowsToAttributeText(editAttributeRows.value)
        editProductForm.features_text = rowsToFeatureText(editFeatureRows.value)
        editProductForm.attribute_options = normalizeAttributeRows(editAttributeRows.value)
        editProductForm.variants = normalizeVariantRows(editVariantRows.value)

        editProductForm
            .transform((data) => transformMoneyFields(data, ['price_cents']))
            .put(route('admin.commerce.products.update', editProductForm.product.id), {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: closeEditProduct,
            })
    }

    return {
        stockAdjustments,
        productForm,
        productAttributeRows,
        productFeatureRows,
        productVariantRows,
        rejectionModal,
        productDeleteModal,
        rejectionReasons,
        editProductForm,
        editAttributeRows,
        editFeatureRows,
        editVariantRows,
        attributePresets,
        presetValuesFor,
        normalizeAttributeRows,
        addProductAttributeRow,
        removeProductAttributeRow,
        addProductVariantRow,
        removeProductVariantRow,
        addProductFeatureRow,
        removeProductFeatureRow,
        addEditAttributeRow,
        removeEditAttributeRow,
        addEditVariantRow,
        removeEditVariantRow,
        addEditFeatureRow,
        removeEditFeatureRow,
        setProductImageUpload,
        setProductGalleryUploads,
        setEditProductImageUpload,
        setEditProductGalleryUploads,
        storeProduct,
        updateProductStatus,
        submitRejection,
        openDeleteProduct,
        closeDeleteProduct,
        setProductDeleteConfirmation,
        destroyProduct,
        selectRejectionReason,
        updateProductStock,
        inventoryAdjustment,
        setInventoryAdjustment,
        adjustInventoryStock,
        adjustGlobalStock,
        updateSellerApplication,
        openEditProduct,
        closeEditProduct,
        submitEditProduct,
    }
}



