import { describe, it, expect } from 'vitest'
import router, { materialListRoute } from '../router'

describe('materialListRoute', () => {
  const camp = {
    id: '42',
    shortTitle: 'this is irrelevant',
    _meta: { loading: false },
  }

  it('returns empty object if camp is loading', () => {
    const loadingCamp = { ...camp, _meta: { loading: true } }
    expect(materialListRoute(loadingCamp)).toEqual({})
  })

  it('returns route for default string argument "/all"', () => {
    const result = materialListRoute(camp)
    expect(result).toEqual({
      name: 'camp/material/all',
      params: {
        campId: '42',
        campShortTitle: 'this-is-irreleva',
      },
      query: {},
    })
  })

  it('returns route for specific string argument "/unassigned"', () => {
    const result = materialListRoute(camp, '/unassigned')
    expect(result).toEqual({
      name: 'camp/material/unassigned',
      params: {
        campId: '42',
        campShortTitle: 'this-is-irreleva',
      },
      query: {},
    })
  })

  it('returns route for a material list object', () => {
    const materialList = {
      id: '42',
      name: 'this is irrelevant',
      _meta: { loading: false },
    }
    const result = materialListRoute(camp, materialList)
    expect(result).toEqual({
      name: 'camp/material/detail',
      params: {
        campId: '42',
        campShortTitle: 'this-is-irreleva',
        materialId: '42',
        materialName: 'this-is-irrelevant',
      },
      query: {},
    })
  })

  it('returns empty object if material list object is missing _meta', () => {
    const materialList = {
      id: '42',
      name: 'this is irrelevant',
    }
    expect(materialListRoute(camp, materialList)).toEqual({})
  })

  it('returns empty object if material list is loading', () => {
    const materialList = {
      id: '42',
      name: 'this is irrelevant',
      _meta: { loading: true },
    }
    expect(materialListRoute(camp, materialList)).toEqual({})
  })

  it('correctly includes query parameters for /all', () => {
    const query = { search: 'test' }
    const result = materialListRoute(camp, '/all', query)
    expect(result.query).toEqual(query)
  })

  it('correctly includes query parameters for materialList', () => {
    const query = { search: 'test' }
    const materialList = {
      id: '42',
      name: 'this is irrelevant',
      _meta: { loading: false },
    }
    const resultWithObject = materialListRoute(camp, materialList, query)
    expect(resultWithObject.query).toEqual(query)
  })
})

describe('camp import route', () => {
  it('matches the import route and not the camp detail route', () => {
    const resolved = router.resolve('/camps/hitobito/pbsmidata/import')
    expect(resolved.name).toBe('camps/import')
    expect(resolved.params.provider).toBe('pbsmidata')
    expect(resolved.params.campId).toBeUndefined()
  })

  it('still matches the camp detail route for a normal camp', () => {
    const resolved = router.resolve('/camps/25a82475e0b7/sola-2026')
    expect(resolved.params.campId).toBe('25a82475e0b7')
  })

  it('passes provider and eventId to the view', () => {
    const resolved = router.resolve('/camps/hitobito/pbsmidata/import?eventId=123')
    const props = resolved.matched[0].props.default(resolved)
    expect(props).toEqual({ provider: 'pbsmidata', eventId: '123' })
  })

  it('passes a null eventId when the query parameter is absent', () => {
    const resolved = router.resolve('/camps/hitobito/pbsmidata/import')
    const props = resolved.matched[0].props.default(resolved)
    expect(props).toEqual({ provider: 'pbsmidata', eventId: null })
  })
})
